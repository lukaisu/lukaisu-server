"""
Content extraction API endpoints.

Outbound edge for the local-first client: turns a URL, an uploaded EPUB, or a
YouTube video into importable text. This is the HTTP surface over the
:mod:`app.services.extract` services, which port three PHP classes —
``WebPageExtractor`` (web), ``EpubParserService`` (epub) and
``YouTubeApiHandler`` (youtube).

Every URL fetch goes through the SSRF-safe util. The heavy/optional libraries
(``trafilatura``, ``ebooklib``/``bs4``, ``youtube_transcript_api``) are
lazy-imported inside the service layer, so this router still mounts when one of
them is missing — the dependency error is surfaced per request as a ``503``.
"""

from fastapi import APIRouter, File, HTTPException, Query, UploadFile
from pydantic import BaseModel

from app.services.extract import epub, web, youtube

router = APIRouter()


class WebExtractRequest(BaseModel):
    """Request to extract readable text from a web page URL."""

    url: str
    title_hint: str = ""


class EpubExtractRequest(BaseModel):
    """Request to fetch and parse an EPUB from a URL."""

    url: str


@router.post("/web")
async def extract_web(request: WebExtractRequest):
    """
    Fetch a web page and extract its title + main readable text.

    Returns ``{title, text, sourceUri}``. When no readable text can be
    extracted, responds ``422`` with ``{error}``.
    """
    url = request.url.strip()
    if not url:
        raise HTTPException(400, "Missing required parameter: url")

    result = await web.extract_from_url(url, request.title_hint)
    if "error" in result:
        raise HTTPException(422, result["error"])
    return result


@router.post("/epub")
async def extract_epub(request: EpubExtractRequest):
    """
    Fetch an EPUB from a URL and extract its metadata, chapters and text.

    Returns ``{metadata, chapters, text}``. When the book has too little
    readable text (likely image-only), responds ``422`` with ``{error}``.
    """
    url = request.url.strip()
    if not url:
        raise HTTPException(400, "Missing required parameter: url")

    result = await epub.extract_from_url(url)
    if "error" in result:
        raise HTTPException(422, result["error"])
    return result


@router.post("/epub/upload")
async def extract_epub_upload(file: UploadFile = File(...)):
    """
    Parse an uploaded EPUB file and extract its metadata, chapters and text.

    The upload must be a ZIP-based EPUB (the bytes start with the ``PK`` magic
    number). Returns ``{metadata, chapters, text}``, or ``422`` with ``{error}``
    when the book has too little readable text.
    """
    data = await file.read()
    if data[:2] != b"PK":
        raise HTTPException(400, "Uploaded file is not a valid EPUB (ZIP) archive")

    result = epub.parse_bytes(data, source_uri=file.filename or "")
    if "error" in result:
        raise HTTPException(422, result["error"])
    return result


@router.get("/youtube/configured")
async def youtube_configured():
    """Report whether a YouTube Data API key is configured on this instance."""
    return {"configured": youtube.is_configured()}


@router.get("/youtube/video")
async def youtube_video(
    video_id: str = Query(..., description="YouTube video ID"),
):
    """
    Fetch a YouTube video's title and description via the Data API v3.

    Requires a configured API key. Returns
    ``{title, description, source_url}``.
    """
    return await youtube.get_video_info(video_id)


@router.get("/youtube/transcript")
async def youtube_transcript(
    video_id: str = Query(..., description="YouTube video ID"),
    language: str | None = Query(None, description="Preferred transcript language"),
):
    """
    Fetch a YouTube video's transcript.

    Tries the requested ``language`` first, then falls back to any available /
    auto-generated transcript. Returns ``{video_id, language, text, segments}``.
    """
    return youtube.get_transcript(video_id, language)
