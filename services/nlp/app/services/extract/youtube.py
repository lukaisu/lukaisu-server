"""
YouTube video info and transcript extraction.

Python port of the PHP ``YouTubeApiHandler``
(``src/Modules/Text/Http/YouTubeApiHandler.php``). ``get_video_info`` proxies the
YouTube Data API v3 (keeping the API key server-side, fetched via the SSRF-safe
util); ``get_transcript`` pulls captions via ``youtube_transcript_api`` (no key
required — lazy-imported so the router still mounts without it).
"""

from __future__ import annotations

import re

from fastapi import HTTPException

from app.config import settings
from app.services.http.safe_fetch import FetchError, safe_get

_YOUTUBE_API_BASE = "https://www.googleapis.com/youtube/v3"
# YouTube IDs are 11 chars in practice; allow 10-12 for safety (mirrors PHP).
_VIDEO_ID_RE = re.compile(r"^[a-zA-Z0-9_-]{10,12}$")


def is_configured() -> bool:
    """Whether a YouTube Data API key is configured (for the video-info path)."""
    return bool(settings.yt_api_key)


def _validate_video_id(video_id: str) -> None:
    """Raise HTTP 400 unless ``video_id`` matches the expected ID format."""
    if not _VIDEO_ID_RE.match(video_id or ""):
        raise HTTPException(400, "Invalid video ID format.")


async def get_video_info(video_id: str) -> dict:
    """Fetch a video's title/description from the YouTube Data API v3.

    Requires a configured API key. Returns
    ``{"title", "description", "source_url"}``.
    """
    _validate_video_id(video_id)

    if not settings.yt_api_key:
        raise HTTPException(503, "YouTube API key not configured")

    import json

    try:
        result = await safe_get(
            f"{_YOUTUBE_API_BASE}/videos",
            timeout=10.0,
            accept="application/json",
            params={
                "part": "snippet",
                "id": video_id,
                "key": settings.yt_api_key,
            },
        )
    except FetchError as exc:
        if exc.status_code == 403 or "403" in exc.message:
            raise HTTPException(403, "Invalid API key or quota exceeded") from exc
        raise HTTPException(exc.status_code, exc.message) from exc

    try:
        data = json.loads(result.text)
    except json.JSONDecodeError as exc:
        raise HTTPException(502, "Invalid response from YouTube API.") from exc

    items = data.get("items") if isinstance(data, dict) else None
    if not items:
        raise HTTPException(404, "Video not found")

    snippet = items[0].get("snippet", {}) if isinstance(items[0], dict) else {}

    return {
        "title": snippet.get("title", ""),
        "description": snippet.get("description", ""),
        "source_url": f"https://youtube.com/watch?v={video_id}",
    }


def _list_transcripts(api_cls, video_id: str):
    """Return a TranscriptList, tolerant of both library API generations.

    ``youtube-transcript-api`` 1.x replaced the 0.x classmethod
    ``YouTubeTranscriptApi.list_transcripts(video_id)`` with an instance method
    ``YouTubeTranscriptApi().list(video_id)``. Support whichever is present.
    """
    if hasattr(api_cls, "list_transcripts"):  # 0.x
        return api_cls.list_transcripts(video_id)
    return api_cls().list(video_id)  # 1.x


def _snippet_field(entry, name: str, default):
    """Read a transcript snippet field as either an object attr (1.x) or dict key (0.x)."""
    if isinstance(entry, dict):
        return entry.get(name, default)
    return getattr(entry, name, default)


def get_transcript(video_id: str, language: str | None = None) -> dict:
    """Fetch a video's transcript via ``youtube_transcript_api``.

    Tries the requested ``language`` first, then falls back to any available /
    auto-generated transcript. Returns
    ``{"video_id", "language", "text", "segments"}``. The underlying library is
    synchronous; calling it directly here is fine. Works against both the 0.x
    and 1.x ``youtube-transcript-api`` APIs (the snippet shape and entry points
    changed between them).
    """
    _validate_video_id(video_id)

    try:
        from youtube_transcript_api import (
            YouTubeTranscriptApi,
            TranscriptsDisabled,
            NoTranscriptFound,
        )
    except ImportError as exc:
        raise HTTPException(
            503,
            "YouTube transcript unavailable: youtube_transcript_api is not installed",
        ) from exc

    try:
        transcript_list = _list_transcripts(YouTubeTranscriptApi, video_id)

        languages = [language] if language else []
        transcript = None
        if languages:
            try:
                transcript = transcript_list.find_transcript(languages)
            except NoTranscriptFound:
                transcript = None

        if transcript is None:
            # Fall back to the first available transcript (manual or generated).
            transcript = next(iter(transcript_list))

        resolved_language = getattr(transcript, "language_code", None) or (
            language or ""
        )
        fetched = transcript.fetch()
    except (TranscriptsDisabled, NoTranscriptFound) as exc:
        raise HTTPException(
            404, "No transcript available for this video"
        ) from exc
    except StopIteration as exc:
        raise HTTPException(
            404, "No transcript available for this video"
        ) from exc

    segments = [
        {
            "start": float(_snippet_field(entry, "start", 0.0)),
            "duration": float(_snippet_field(entry, "duration", 0.0)),
            "text": str(_snippet_field(entry, "text", "")),
        }
        for entry in fetched
    ]
    text = " ".join(seg["text"] for seg in segments if seg["text"]).strip()

    return {
        "video_id": video_id,
        "language": resolved_language,
        "text": text,
        "segments": segments,
    }
