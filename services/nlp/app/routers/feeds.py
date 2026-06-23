"""
RSS / Atom feed parsing API endpoints.

Outbound edge for the local-first client: takes a feed URL, fetches it through
the SSRF-safe util, and returns normalized article items. This is the HTTP
surface over :mod:`app.services.feeds.rss` (a port of the PHP ``RssParser``).

``feedparser`` is imported lazily by the service, so this router still mounts
even when the parsing library is absent — the dependency error is surfaced per
request as a ``503`` instead of failing the whole boot.
"""
from fastapi import APIRouter, HTTPException, Query
from pydantic import BaseModel

from app.services.feeds import rss
from app.services.http.safe_fetch import FetchError

router = APIRouter()


class ParseFeedRequest(BaseModel):
    """Request to fetch and parse an RSS/Atom feed."""

    url: str
    article_section: str = ""


@router.post("/parse")
async def parse_feed(request: ParseFeedRequest):
    """
    Fetch and parse an RSS or Atom feed.

    Returns the feed-level title, a ``feed_text`` capability hint, and the list
    of article items (``title``, ``link``, ``desc``, ``date``, ``audio``,
    ``text``). Both RSS 2.0 and Atom are supported.
    """
    url = request.url.strip()
    if not url:
        raise HTTPException(400, "Missing required parameter: url")

    try:
        return await rss.parse_feed(url, request.article_section)
    except ImportError:
        raise HTTPException(
            503, "RSS parsing unavailable: feedparser is not installed"
        )
    except FetchError as exc:
        raise HTTPException(exc.status_code, exc.message)


@router.get("/title")
async def feed_title(url: str = Query(..., description="Feed URL")):
    """
    Fetch a feed and return only its feed-level title.

    Useful for naming a feed subscription without parsing every article.
    """
    url = url.strip()
    if not url:
        raise HTTPException(400, "Missing required parameter: url")

    try:
        title = await rss.get_feed_title(url)
    except ImportError:
        raise HTTPException(
            503, "RSS parsing unavailable: feedparser is not installed"
        )
    except FetchError as exc:
        raise HTTPException(exc.status_code, exc.message)

    return {"title": title}
