"""
Project Gutenberg content discovery (via the Gutendex catalog API).

Python port of the PHP ``GutenbergClient``
(``src/Shared/Infrastructure/Http/GutenbergClient.php``). Searches the Gutendex
catalog (``gutendex.com``) for free public-domain e-books, normalises each book
record, and resolves a plain-text download URL. ``fetch_text`` downloads a book
and strips the standard Project Gutenberg header/footer boilerplate.

All outbound requests go through the SSRF-safe fetch util so every hop (including
redirects out of gutendex.com) is re-validated.
"""

from __future__ import annotations

import re

from app.config import settings
from app.services.http.safe_fetch import safe_get, safe_get_json

# Matches Project Gutenberg's start/end boilerplate markers, tolerant of the
# "THE"/"THIS" wording variants and any trailing book title text, e.g.:
#   *** START OF THE PROJECT GUTENBERG EBOOK MOBY DICK ***
#   *** END OF THIS PROJECT GUTENBERG EBOOK ... ***
_START_MARKER = re.compile(
    r"\*\*\*\s*START OF (?:THE|THIS) PROJECT GUTENBERG EBOOK.*?\*\*\*",
    re.IGNORECASE | re.DOTALL,
)
_END_MARKER = re.compile(
    r"\*\*\*\s*END OF (?:THE|THIS) PROJECT GUTENBERG EBOOK.*?\*\*\*",
    re.IGNORECASE | re.DOTALL,
)


def _extract_text_url(book: dict) -> str:
    """Pick the best plain-text download URL from a Gutendex book record.

    Prefers an explicitly UTF-8 / ``.txt`` plain-text format, falling back to
    any ``text/plain`` entry. Returns "" when the book has no plain text.
    """
    formats = book.get("formats") or {}
    if not isinstance(formats, dict):
        return ""

    plain_entries = [
        (mime, url)
        for mime, url in formats.items()
        if isinstance(mime, str) and "text/plain" in mime and isinstance(url, str)
    ]

    # Prefer an explicitly UTF-8 entry (matches the PHP client's precedence),
    # then a ``.txt`` / "utf-8" URL, then any plain-text entry.
    for mime, url in plain_entries:
        if "utf-8" in mime.lower():
            return url
    for mime, url in plain_entries:
        lower_url = url.lower()
        if lower_url.endswith(".txt") or "utf-8" in lower_url:
            return url
    if plain_entries:
        return plain_entries[0][1]

    return ""


def _map_book(book: dict) -> dict:
    """Normalise a raw Gutendex book record into the API result shape."""
    authors: list[str] = []
    for author in book.get("authors") or []:
        if isinstance(author, dict):
            authors.append(str(author.get("name", "")))

    subjects = book.get("subjects") or []
    if not isinstance(subjects, list):
        subjects = []

    languages = book.get("languages") or []
    if not isinstance(languages, list):
        languages = []

    return {
        "id": int(book.get("id") or 0),
        "title": str(book.get("title") or ""),
        "authors": authors,
        "languages": languages,
        "subjects": [str(s) for s in subjects[:3]],
        "downloadCount": int(book.get("download_count") or 0),
        "textUrl": _extract_text_url(book),
    }


async def search(
    query: str, language_code: str | None = None, page: int = 1
) -> dict:
    """Search the Project Gutenberg catalog via Gutendex.

    Maps each upstream book into the normalised result shape. Top-level keys:
    ``results`` (list), ``count`` (total matches), ``next`` (more pages exist).
    """
    params: dict[str, object] = {}
    if query:
        params["search"] = query
    if language_code:
        params["languages"] = language_code.lower()
    if page > 1:
        params["page"] = page

    response = await safe_get_json(settings.gutendex_base_url, params=params)
    if not isinstance(response, dict):
        response = {}

    raw_results = response.get("results") or []
    results = [_map_book(b) for b in raw_results if isinstance(b, dict)]

    return {
        "results": results,
        "count": int(response.get("count") or 0),
        "next": response.get("next") is not None,
    }


async def browse(language_code: str, page: int = 1) -> dict:
    """Browse popular books for a language (search with an empty query)."""
    return await search("", language_code, page)


async def fetch_text(url: str) -> str:
    """Download a Gutenberg plain-text book and strip its boilerplate.

    Everything before the ``*** START OF ... ***`` marker and after the
    ``*** END OF ... ***`` marker is removed. When the markers are absent the
    full text is returned unchanged.
    """
    result = await safe_get(
        url, timeout=15.0, max_bytes=2_000_000, accept="text/plain"
    )
    text = result.text

    start = _START_MARKER.search(text)
    if start:
        text = text[start.end():]

    end = _END_MARKER.search(text)
    if end:
        text = text[: end.start()]

    return text.strip()
