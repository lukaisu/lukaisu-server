"""
Web article text extraction.

Python port of the PHP ``WebPageExtractor``
(``src/Shared/Infrastructure/Http/WebPageExtractor.php``). Fetches a URL through
the SSRF-safe fetch util, rejects binary payloads, and extracts a title + the
main readable body.

Unlike the PHP version (which hand-rolls a DOM/XPath noise-stripping pipeline),
this uses ``trafilatura`` for HTML article extraction — the de-facto Python tool
for the job — while keeping the same plain-text unwrapping behaviour for raw
``.txt`` bodies. ``trafilatura`` is lazy-imported so the router still mounts when
it is not installed.
"""

from __future__ import annotations

import re
from urllib.parse import unquote, urlsplit

from fastapi import HTTPException

from app.services.http.safe_fetch import FetchError, safe_get

# Firefox UA mirrors the PHP fetch — some sites block default/bot agents.
_USER_AGENT = "Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0"
_ACCEPT = "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"

# Detects the presence of any HTML-like tag (mirrors the PHP isPlainText check).
_HTML_TAG_RE = re.compile(r"<[a-z!/][^>]*>", re.IGNORECASE)
# Quick structural markers that definitively flag an HTML document.
_HTML_STRUCT_RE = re.compile(r"<(?:html|body)\b", re.IGNORECASE)
# Fallback title extraction from raw HTML when trafilatura yields no metadata.
_OG_TITLE_RE = re.compile(
    r'<meta\s+[^>]*property=["\']og:title["\'][^>]*content=["\']([^"\']+)["\']',
    re.IGNORECASE,
)
_TITLE_TAG_RE = re.compile(r"<title[^>]*>(.*?)</title>", re.IGNORECASE | re.DOTALL)

# Minimum number of characters trafilatura must yield to count as "readable".
_MIN_TEXT_LEN = 50


def _title_from_url(url: str) -> str:
    """Derive a human-readable title from a URL's last path segment.

    Mirrors the PHP ``titleFromUrl``: strip the extension and turn common
    separators (``-`` ``_`` ``.``) into spaces.
    """
    path = urlsplit(url).path
    filename = unquote(path.rsplit("/", 1)[-1]) if path else ""
    if "." in filename:
        filename = filename.rsplit(".", 1)[0]
    name = re.sub(r"[-_.]+", " ", filename).strip()
    return name


def _unwrap_hard_line_breaks(text: str) -> str:
    """Join hard-wrapped (~72-char) lines back into paragraphs.

    Consecutive non-blank lines are joined with a space; a blank line marks a
    paragraph break. Mirrors the PHP ``unwrapHardLineBreaks``.
    """
    paragraphs: list[str] = []
    buffer = ""
    for raw_line in text.split("\n"):
        trimmed = raw_line.strip()
        if trimmed == "":
            if buffer != "":
                paragraphs.append(buffer)
                buffer = ""
            paragraphs.append("")
            continue
        buffer = trimmed if buffer == "" else f"{buffer} {trimmed}"
    if buffer != "":
        paragraphs.append(buffer)
    # Collapse runs of blank paragraph markers down to a single break.
    out = "\n".join(paragraphs)
    out = re.sub(r"\n{3,}", "\n\n", out)
    return out.strip()


def _looks_binary(content: bytes) -> bool:
    """Return True when the first 512 bytes contain a null byte (binary)."""
    return b"\x00" in content[:512]


def _is_plain_text(text: str, content_type: str) -> bool:
    """Heuristic: no HTML tags, or an explicit ``text/plain`` content type."""
    if _HTML_STRUCT_RE.search(text):
        return False
    if "text/plain" in content_type.lower():
        return True
    return _HTML_TAG_RE.search(text) is None


def _title_from_html(html: str) -> str:
    """Best-effort title from raw HTML: og:title then <title>."""
    match = _OG_TITLE_RE.search(html)
    if match and match.group(1).strip():
        return match.group(1).strip()
    match = _TITLE_TAG_RE.search(html)
    if match and match.group(1).strip():
        return re.sub(r"\s+", " ", match.group(1)).strip()
    return ""


async def extract_from_url(url: str, title_hint: str = "") -> dict:
    """Fetch ``url`` and extract a title + readable body text.

    Returns ``{"title", "text", "sourceUri"}`` on success, or
    ``{"error": ...}`` when no readable text could be extracted (the router
    maps that onto a 422). Raises :class:`HTTPException` for fetch failures and
    non-HTML/binary payloads.
    """
    try:
        result = await safe_get(
            url,
            timeout=15.0,
            max_bytes=2_000_000,
            max_redirects=5,
            accept=_ACCEPT,
            user_agent=_USER_AGENT,
        )
    except FetchError as exc:
        raise HTTPException(exc.status_code, exc.message) from exc

    if _looks_binary(result.content):
        raise HTTPException(422, "URL is not an HTML/text page")

    body = result.text

    # Plain-text path: unwrap hard line breaks into paragraphs and return.
    if _is_plain_text(body, result.content_type):
        text = _unwrap_hard_line_breaks(body)
        title = title_hint or _title_from_url(result.url)
        return {"title": title, "text": text, "sourceUri": result.url}

    # HTML path: delegate article extraction to trafilatura (lazy import).
    try:
        import trafilatura
    except ImportError as exc:
        raise HTTPException(
            503,
            "Web extraction unavailable: trafilatura is not installed",
        ) from exc

    text = trafilatura.extract(
        body,
        include_comments=False,
        include_tables=False,
    )

    if not text or len(text.strip()) < _MIN_TEXT_LEN:
        return {"error": "Could not extract readable text from this page."}

    # Title preference: trafilatura metadata → og:title/<title> → URL basename.
    title = ""
    metadata = trafilatura.extract_metadata(body)
    if metadata is not None and getattr(metadata, "title", None):
        title = str(metadata.title).strip()
    if not title:
        title = _title_from_html(body)
    if not title:
        title = title_hint or _title_from_url(result.url)

    return {"title": title, "text": text.strip(), "sourceUri": result.url}
