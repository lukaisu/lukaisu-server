"""
Global Digital Library (GDL) content discovery.

Python port of the PHP ``GdlClient``
(``src/Shared/Infrastructure/Http/GdlClient.php``). Searches the GDL content API
(``content.digitallibrary.io``, a WordPress REST API) for openly-licensed
early-grade readers. GDL aggregates content from StoryWeaver, African Storybook
and others under CC-BY / CC-BY-SA. Books are ePUB; this layer only deals in
catalog metadata and the raw ePUB generator URL.

Field mapping is best-effort against the live GDL WordPress API shape: the
captured response lists posts under ``books`` with a ``meta.count`` total, the
reading level surfaces as a ``topic[]`` term named "Level N" (the ``level``
taxonomy is unpopulated), and ``thumbnail`` is ``false`` when a book has no
cover. We look up the post list defensively across a few candidate keys and use
``.get()`` with defaults throughout so missing fields degrade gracefully.
"""

from __future__ import annotations

import html
import re

from app.config import settings
from app.services.http.safe_fetch import safe_get_json

PAGE_SIZE = 20

# Reading-level label, e.g. "Level 3" (case-insensitive, tolerant of spacing).
_LEVEL_PATTERN = re.compile(r"^Level\s*\d+", re.IGNORECASE)
_LEVEL_NUMBER = re.compile(r"(\d+)")


def _decode(text: object) -> str:
    """HTML-entity-decode a GDL text field (titles/descriptions are encoded)."""
    if not text:
        return ""
    return html.unescape(str(text))


def level_to_tier(level: str) -> str:
    """Map a GDL reading level label to a coarse difficulty tier.

    Levels 1-2 -> "easy", 3 -> "medium", 4-5 -> "hard". Returns "" when the
    label carries no number (the PHP client defaults to "medium"; here an
    unknown level yields an empty tier since the result mapping starts from "").
    """
    match = _LEVEL_NUMBER.search(level)
    if not match:
        return ""
    n = int(match.group(1))
    if n <= 2:
        return "easy"
    if n >= 4:
        return "hard"
    return "medium"


def _extract_level(book: dict) -> str:
    """Read the "Level N" label from a book's ``topic[]`` terms ("" if absent)."""
    for topic in book.get("topic") or []:
        if isinstance(topic, dict):
            name = str(topic.get("name", ""))
            if _LEVEL_PATTERN.match(name):
                return name
    return ""


def _first_term_slug(terms: object) -> str:
    """Slug of the first term in a GDL taxonomy array ("" when empty)."""
    if isinstance(terms, list) and terms and isinstance(terms[0], dict):
        return str(terms[0].get("slug", ""))
    return ""


def _first_term_name(terms: object) -> str:
    """Name of the first term in a GDL taxonomy array ("" when empty)."""
    if isinstance(terms, list) and terms and isinstance(terms[0], dict):
        return str(terms[0].get("name", ""))
    return ""


def _thumbnail_url(book: dict) -> str:
    """Cover thumbnail URL; GDL returns ``false`` (not a string) when absent."""
    thumb = book.get("thumbnail")
    return thumb if isinstance(thumb, str) else ""


def _posts_from_response(response: dict) -> list:
    """Extract the post list defensively across candidate GDL response keys."""
    for key in ("hits", "results", "books"):
        value = response.get(key)
        if isinstance(value, list):
            return value
    return []


def _map_post(book: dict) -> dict:
    """Normalise a raw GDL post record into the API result shape."""
    level = _extract_level(book)
    return {
        "id": int(book.get("postId") or 0),
        "title": _decode(book.get("title", "")),
        "publisher": _decode(book.get("publisher", "")),
        "description": _decode(book.get("description", "")).strip(),
        "language": _first_term_slug(book.get("language")),
        "license": _first_term_name(book.get("license")),
        "level": level,
        "difficultyTier": level_to_tier(level),
        "thumbnail": _thumbnail_url(book),
        "sourceUri": str(book.get("postLink") or ""),
        "epubUrl": str(book.get("epubUrl") or "").strip(),
    }


async def search(
    query: str, language_code: str | None = None, page: int = 1
) -> dict:
    """Search the Global Digital Library catalog.

    Top-level keys: ``results`` (list), ``count`` (total across pages, from
    ``meta.count``), ``next`` (whether ``page * PAGE_SIZE`` < ``count``).
    """
    params: dict[str, object] = {}
    if query:
        params["query"] = query
    if language_code:
        params["language"] = language_code.lower()
    if page > 1:
        params["_skip"] = (page - 1) * PAGE_SIZE

    response = await safe_get_json(settings.gdl_base_url, params=params)
    if not isinstance(response, dict):
        response = {}

    posts = _posts_from_response(response)
    results = [_map_post(p) for p in posts if isinstance(p, dict)]

    meta = response.get("meta")
    if isinstance(meta, dict):
        count = int(meta.get("count") or len(results))
    else:
        count = len(results)

    return {
        "results": results,
        "count": count,
        "next": (page * PAGE_SIZE) < count,
    }


async def browse(language_code: str, page: int = 1) -> dict:
    """Browse books for a language (search with an empty query)."""
    return await search("", language_code, page)
