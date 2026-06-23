"""
EPUB parsing and text extraction.

Python port of the PHP ``EpubParserService``
(``src/Modules/Book/Application/Services/EpubParserService.php``). Reads an EPUB
(either fetched from a URL via the SSRF-safe util or supplied as raw bytes),
walks its document items in spine order, converts each chapter's HTML to plain
text, and returns normalised metadata + chapters + the joined full text.

``ebooklib`` and ``bs4`` (BeautifulSoup) are lazy-imported inside the parsing
function so this router still mounts when they are not installed.
"""

from __future__ import annotations

import os
import re
import tempfile

from fastapi import HTTPException

from app.services.http.safe_fetch import FetchError, safe_get

# Cap total extracted text — a guard against absurdly large / malicious books.
_MAX_TEXT_CHARS = 5_000_000
# Below this many words the book is almost certainly image-only (picture book).
_MIN_WORDS = 30
# A chapter title candidate must be short and contain no period.
_MAX_TITLE_LEN = 100
# Documents yielding less than this many characters are treated as nav/toc noise.
_MIN_DOC_CHARS = 30

_NAV_HINTS = ("nav", "toc")


async def extract_from_url(url: str) -> dict:
    """Fetch an EPUB from ``url`` and parse it.

    Routes through the SSRF-safe fetch util with EPUB-appropriate caps, then
    delegates to :func:`parse_bytes`.
    """
    try:
        result = await safe_get(
            url,
            timeout=30.0,
            max_bytes=20_000_000,
            accept="application/epub+zip",
        )
    except FetchError as exc:
        raise HTTPException(exc.status_code, exc.message) from exc

    return parse_bytes(result.content, result.url)


def _normalize_whitespace(text: str) -> str:
    """Collapse whitespace while preserving paragraph (double-newline) breaks."""
    text = text.replace("\r", "\n")
    # Collapse runs of spaces/tabs.
    text = re.sub(r"[ \t]+", " ", text)
    # Trim each line.
    lines = [line.strip() for line in text.split("\n")]
    text = "\n".join(lines)
    # Collapse 3+ newlines into a paragraph break.
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text.strip()


def _is_navigation_doc(filename: str, text: str) -> bool:
    """Skip nav/toc documents: name hints or near-empty text content."""
    lowered = (filename or "").lower()
    if any(hint in lowered for hint in _NAV_HINTS):
        return True
    return len(text.strip()) < _MIN_DOC_CHARS


def _chapter_title(text: str, num: int) -> str:
    """First non-empty line if it looks like a title, else ``Chapter N``.

    Mirrors the PHP ``extractTitleFromContent``: a candidate title must be
    shorter than 100 chars and contain no period.
    """
    for line in text.split("\n"):
        first = line.strip()
        if first:
            if len(first) < _MAX_TITLE_LEN and "." not in first:
                return first
            break
    return f"Chapter {num}"


def _first_metadata(book, namespace: str, name: str) -> str | None:
    """Return the first Dublin Core metadata value, or None when absent."""
    try:
        entries = book.get_metadata(namespace, name)
    except Exception:  # noqa: BLE001 - tolerate malformed metadata
        return None
    if not entries:
        return None
    value = entries[0][0] if isinstance(entries[0], (tuple, list)) else entries[0]
    if value is None:
        return None
    value = str(value).strip()
    return value or None


def parse_bytes(data: bytes, source_uri: str = "") -> dict:
    """Parse EPUB ``data`` (in-memory bytes) into metadata, chapters and text.

    ``ebooklib`` requires a filesystem path, so the bytes are written to a
    temporary ``.epub`` file, read, and unlinked (try/finally). Returns:

    ``{"metadata": {...}, "chapters": [...], "text": ...}`` or
    ``{"error": ...}`` when the book has too little readable text.
    """
    try:
        import ebooklib
        from ebooklib import epub
    except ImportError as exc:
        raise HTTPException(
            503,
            "EPUB extraction unavailable: ebooklib is not installed",
        ) from exc

    try:
        from bs4 import BeautifulSoup
    except ImportError as exc:
        raise HTTPException(
            503,
            "EPUB extraction unavailable: bs4 is not installed",
        ) from exc

    tmp_path = ""
    try:
        with tempfile.NamedTemporaryFile(suffix=".epub", delete=False) as tmp:
            tmp.write(data)
            tmp_path = tmp.name
        try:
            book = epub.read_epub(tmp_path)
        except Exception as exc:  # noqa: BLE001 - corrupt / non-EPUB input
            raise HTTPException(422, f"Could not read EPUB file: {exc}") from exc
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.remove(tmp_path)

    metadata = {
        "title": _first_metadata(book, "DC", "title") or "Unknown Title",
        "author": _first_metadata(book, "DC", "creator"),
        "description": _first_metadata(book, "DC", "description"),
        "language": _first_metadata(book, "DC", "language"),
        "sourceUri": source_uri,
    }

    chapters: list[dict] = []
    total_chars = 0
    chapter_num = 1

    for item in book.get_items_of_type(ebooklib.ITEM_DOCUMENT):
        filename = item.get_name() or ""
        try:
            raw = item.get_content()
        except Exception:  # noqa: BLE001 - skip unreadable items
            continue
        soup = BeautifulSoup(raw, "html.parser")
        text = _normalize_whitespace(soup.get_text("\n"))

        if _is_navigation_doc(filename, text):
            continue
        if not text:
            continue

        # Enforce the total-text cap (zip-bomb / absurd-content guard).
        if total_chars + len(text) > _MAX_TEXT_CHARS:
            text = text[: max(0, _MAX_TEXT_CHARS - total_chars)]
        total_chars += len(text)

        chapters.append(
            {
                "num": chapter_num,
                "title": _chapter_title(text, chapter_num),
                "content": text,
            }
        )
        chapter_num += 1

        if total_chars >= _MAX_TEXT_CHARS:
            break

    full_text = "\n\n".join(ch["content"] for ch in chapters)

    if len(full_text.split()) < _MIN_WORDS:
        return {
            "error": "This book has too little readable text — "
            "it is likely an image-only picture book."
        }

    return {"metadata": metadata, "chapters": chapters, "text": full_text}
