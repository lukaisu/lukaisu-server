"""
RSS / Atom feed parsing service.

Python port of the PHP ``RssParser``
(``src/Modules/Feed/Application/Services/RssParser.php``). The PHP version
hand-parsed the feed XML with ``DOMDocument`` and branched on RSS-2.0 vs Atom
tag names; here we delegate to ``feedparser``, which normalizes both formats
into a single entry shape, so the dual-format handling comes for free.

Network access goes exclusively through the SSRF-safe fetch util
(:func:`app.services.http.safe_fetch.safe_get`) — we never let ``feedparser``
fetch a URL itself, since that would bypass the per-hop redirect re-validation
and private-range blocking. We fetch the raw bytes and hand *those* to
``feedparser.parse``.

``feedparser`` is imported lazily inside the functions so the router still
mounts (and reports its capability) even if the library is not installed.
"""

from __future__ import annotations

import re
import time
from typing import Any

from app.services.http.safe_fetch import safe_get

# Matches the PHP MAX_FEED_BYTES constant (8 MB). Largest legitimate podcast
# feeds are ~3 MB; beyond this is misconfiguration or an OOM attempt.
MAX_FEED_BYTES = 8 * 1024 * 1024

# Total fetch budget for a single feed download, in seconds.
FEED_TIMEOUT = 30.0

# Content negotiation mirroring the PHP fetcher's Accept header.
FEED_ACCEPT = (
    "application/rss+xml, application/atom+xml, "
    "application/xml, text/xml, */*"
)

# Descriptions longer than this are truncated (with an ellipsis appended).
DESC_MAX_CHARS = 1000

# Pre-compiled tag stripper for plain-text conversion.
_TAG_RE = re.compile(r"<[^>]+>")
_WS_RE = re.compile(r"[ \t\r\f\v]+")


def _strip_html(value: str) -> str:
    """Reduce an HTML fragment to trimmed plain text.

    Drops every tag, collapses runs of horizontal whitespace, and trims. This
    is the dependency-free analogue of the PHP cleaner's tag-stripping regexes;
    we keep it deliberately simple since feedparser already decodes entities.
    """
    if not value:
        return ""
    text = _TAG_RE.sub("", value)
    text = _WS_RE.sub(" ", text)
    return text.strip()


def _truncate_desc(desc: str) -> str:
    """Truncate a description to ``DESC_MAX_CHARS`` with a trailing ellipsis.

    Matches the PHP behavior: only strings longer than the cap are touched, and
    the ellipsis replaces the trailing characters rather than being appended on
    top (so the result never exceeds the cap by much).
    """
    if len(desc) <= DESC_MAX_CHARS:
        return desc
    return desc[: DESC_MAX_CHARS - 3].rstrip() + "..."


def _format_date(entry: dict[str, Any]) -> str:
    """Format an entry's publication date as a MySQL datetime string.

    Prefers ``published_parsed`` and falls back to ``updated_parsed`` (both are
    ``time.struct_time`` produced by feedparser). Returns ``""`` when neither is
    present so the caller can decide on a fallback.
    """
    parsed = entry.get("published_parsed") or entry.get("updated_parsed")
    if not parsed:
        return ""
    try:
        return time.strftime("%Y-%m-%d %H:%M:%S", parsed)
    except (TypeError, ValueError):
        return ""


def _extract_audio(entry: dict[str, Any]) -> str:
    """Return the URL of the first audio enclosure, or ``""``.

    Scans ``entry.enclosures`` for an entry whose ``type`` starts with
    ``audio`` (the PHP version only matched ``audio/mpeg``; we broaden to any
    ``audio/*`` MIME). Uses ``href`` and falls back to ``url``.
    """
    for enc in entry.get("enclosures", []) or []:
        enc_type = (enc.get("type") or "").lower()
        if enc_type.startswith("audio"):
            href = enc.get("href") or enc.get("url") or ""
            if href:
                return href
    return ""


def _extract_text(entry: dict[str, Any]) -> str:
    """Return the entry's inline content as plain text, or ``""``.

    feedparser exposes Atom ``content`` (and ``content:encoded``) as a list of
    detail dicts; we take the first one's ``value``. HTML is stripped to text.
    This stays optional — most RSS feeds carry no inline content.
    """
    content = entry.get("content")
    if content and isinstance(content, list):
        value = content[0].get("value", "")
        return _strip_html(value)
    return ""


def _map_entry(entry: dict[str, Any]) -> dict[str, str]:
    """Map one feedparser entry onto the PHP item contract."""
    desc = _truncate_desc(_strip_html(entry.get("summary", "")))
    return {
        "title": entry.get("title", ""),
        "link": entry.get("link", ""),
        "desc": desc,
        "date": _format_date(entry),
        "audio": _extract_audio(entry),
        "text": _extract_text(entry),
    }


def _detect_feed_text(entries: list[dict[str, Any]]) -> str:
    """Report which text source the feed's entries carry.

    Returns ``"content"`` if any entry has inline ``content``, otherwise
    ``"description"`` if any entry has a ``summary``, otherwise ``""``. This is
    the capability hint the PHP ``detectAndParse`` derived from per-item length
    counting, simplified to a presence check.
    """
    has_content = any(entry.get("content") for entry in entries)
    if has_content:
        return "content"
    has_summary = any(entry.get("summary") for entry in entries)
    if has_summary:
        return "description"
    return ""


async def parse_feed(source_uri: str, article_section: str = "") -> dict[str, Any]:
    """Fetch and parse an RSS/Atom feed into normalized items.

    The feed body is fetched through the SSRF-safe util and the raw bytes are
    handed to ``feedparser`` (never a URL). ``article_section`` is accepted for
    parity with the PHP signature; feedparser already normalizes inline content
    into ``entry.content``, so it is not used for tag selection here.

    :param source_uri:      Feed URL.
    :param article_section: Unused tag hint, kept for PHP signature parity.
    :returns: ``{"feed_title", "feed_text", "items": [...]}``.
    :raises app.services.http.safe_fetch.FetchError: on fetch/SSRF failure.
    :raises ImportError: if ``feedparser`` is not installed.
    """
    try:
        import feedparser
    except ImportError:  # surfaced by the router as a 503
        raise

    result = await safe_get(
        source_uri,
        timeout=FEED_TIMEOUT,
        max_bytes=MAX_FEED_BYTES,
        accept=FEED_ACCEPT,
    )

    parsed = feedparser.parse(result.content)
    entries: list[dict[str, Any]] = list(parsed.get("entries", []))

    return {
        "feed_title": parsed.feed.get("title", "") if parsed.feed else "",
        "feed_text": _detect_feed_text(entries),
        "items": [_map_entry(entry) for entry in entries],
    }


async def get_feed_title(source_uri: str) -> str:
    """Fetch a feed and return only its feed-level title (``""`` if absent).

    :param source_uri: Feed URL.
    :raises app.services.http.safe_fetch.FetchError: on fetch/SSRF failure.
    :raises ImportError: if ``feedparser`` is not installed.
    """
    try:
        import feedparser
    except ImportError:
        raise

    result = await safe_get(
        source_uri,
        timeout=FEED_TIMEOUT,
        max_bytes=MAX_FEED_BYTES,
        accept=FEED_ACCEPT,
    )

    parsed = feedparser.parse(result.content)
    return parsed.feed.get("title", "") if parsed.feed else ""
