"""
Content-discovery API endpoints.

The "outbound edge" of the local-first app: it performs the cross-origin catalog
searches a phone cannot make safely. Each source is a Python port of (or a new
sibling to) the PHP content clients:

* ``gutenberg`` — Project Gutenberg via Gutendex (port of ``GutenbergClient``);
* ``gdl`` — Global Digital Library (port of ``GdlClient``);
* ``internet_archive`` — Internet Archive advancedsearch (new source).

Every call funnels through the SSRF-safe fetch util in the service layer. Upstream
failures surface as :class:`FetchError`, which is mapped onto an ``HTTPException``.
"""

from fastapi import APIRouter, HTTPException, Query
from pydantic import BaseModel

from app.services.content import gdl, gutenberg, internet_archive
from app.services.http.safe_fetch import FetchError

router = APIRouter()


class SourceInfo(BaseModel):
    """Descriptor for a content-discovery source."""

    id: str
    name: str
    kind: str


class FetchedText(BaseModel):
    """A fetched, boilerplate-stripped plain-text book body."""

    text: str


def _language_or_none(language: str) -> str | None:
    """Treat an empty/whitespace ``language`` query param as "no filter"."""
    language = language.strip()
    return language or None


@router.get("/sources")
async def list_sources():
    """List the available content-discovery sources."""
    return {
        "sources": [
            SourceInfo(id="gutenberg", name="Project Gutenberg", kind="text"),
            SourceInfo(id="gdl", name="Global Digital Library", kind="epub"),
            SourceInfo(
                id="internet_archive", name="Internet Archive", kind="text"
            ),
        ]
    }


@router.get("/gutenberg")
async def search_gutenberg(
    q: str = "",
    language: str = "",
    page: int = Query(1, ge=1),
):
    """Search Project Gutenberg (browse popular books when ``q`` is empty)."""
    language_code = _language_or_none(language)
    try:
        if q:
            return await gutenberg.search(q, language_code, page)
        return await gutenberg.browse(language_code or "", page)
    except FetchError as exc:
        raise HTTPException(status_code=exc.status_code, detail=exc.message)


@router.get("/gutenberg/text", response_model=FetchedText)
async def gutenberg_text(url: str):
    """Fetch a Gutenberg plain-text book and strip its boilerplate."""
    try:
        text = await gutenberg.fetch_text(url)
    except FetchError as exc:
        raise HTTPException(status_code=exc.status_code, detail=exc.message)
    return FetchedText(text=text)


@router.get("/gdl")
async def search_gdl(
    q: str = "",
    language: str = "",
    page: int = Query(1, ge=1),
):
    """Search the Global Digital Library (browse when ``q`` is empty)."""
    language_code = _language_or_none(language)
    try:
        if q:
            return await gdl.search(q, language_code, page)
        return await gdl.browse(language_code or "", page)
    except FetchError as exc:
        raise HTTPException(status_code=exc.status_code, detail=exc.message)


@router.get("/internet-archive")
async def search_internet_archive(
    q: str = "",
    language: str = "",
    page: int = Query(1, ge=1),
):
    """Search the Internet Archive ``texts`` collection."""
    language_code = _language_or_none(language)
    try:
        if q:
            return await internet_archive.search(q, language_code, page)
        return await internet_archive.browse(language_code or "", page)
    except FetchError as exc:
        raise HTTPException(status_code=exc.status_code, detail=exc.message)
