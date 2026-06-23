"""
Internet Archive content discovery.

New source (no PHP reference) built on the public Internet Archive
advancedsearch API (``archive.org/advancedsearch.php``). Searches the ``texts``
mediatype, optionally constrained by language, and normalises each document into
detail/download URLs. All outbound requests go through the SSRF-safe fetch util.
"""

from __future__ import annotations

from app.config import settings
from app.services.http.safe_fetch import safe_get_json

PAGE_SIZE = 20


def _as_list(value: object) -> list[str]:
    """Coerce an IA field that may be a scalar, a list, or absent into a list."""
    if value is None or value == "":
        return []
    if isinstance(value, list):
        return [str(v) for v in value if v not in (None, "")]
    return [str(value)]


def _map_doc(doc: dict) -> dict:
    """Normalise a raw IA search document into the API result shape."""
    identifier = str(doc.get("identifier") or "")
    return {
        "id": identifier,
        "title": str(doc.get("title") or ""),
        "authors": _as_list(doc.get("creator")),
        "languages": _as_list(doc.get("language")),
        "year": str(doc.get("year") or ""),
        "textUrl": f"https://archive.org/details/{identifier}",
        "downloadUrl": f"https://archive.org/download/{identifier}",
    }


async def search(
    query: str, language_code: str | None = None, page: int = 1
) -> dict:
    """Search the Internet Archive ``texts`` collection.

    Top-level keys: ``results`` (list), ``count`` (``response.numFound``),
    ``next`` (whether ``page * PAGE_SIZE`` < ``numFound``).
    """
    q = "mediatype:texts"
    if query:
        q += f" AND {query}"
    if language_code:
        q += f" AND language:({language_code})"

    params: list[tuple[str, object]] = [
        ("q", q),
        ("fl[]", "identifier"),
        ("fl[]", "title"),
        ("fl[]", "creator"),
        ("fl[]", "language"),
        ("fl[]", "year"),
        ("rows", PAGE_SIZE),
        ("page", page),
        ("output", "json"),
    ]

    response = await safe_get_json(
        settings.internet_archive_base_url, params=params
    )
    if not isinstance(response, dict):
        response = {}

    body = response.get("response")
    if not isinstance(body, dict):
        body = {}

    docs = body.get("docs") or []
    results = [_map_doc(d) for d in docs if isinstance(d, dict)]
    count = int(body.get("numFound") or 0)

    return {
        "results": results,
        "count": count,
        "next": (page * PAGE_SIZE) < count,
    }


async def browse(language_code: str, page: int = 1) -> dict:
    """Browse texts for a language (search with an empty query)."""
    return await search("", language_code, page)
