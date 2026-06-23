"""
SSRF-protected outbound HTTP fetching.

Every outbound integration (content discovery, RSS, web/EPUB extraction) MUST
fetch through this module rather than calling ``httpx`` directly. It is the
Python port of the PHP ``UrlUtilities::safeHttpGet`` guard and reproduces its
defenses:

* scheme allow-list (http/https only);
* per-hop validation — redirects are followed manually and **every** hop's host
  is re-resolved and checked, not just the entry URL (defeats DNS-rebinding and
  redirect-to-internal attacks);
* private / loopback / link-local / reserved / multicast IP ranges are blocked;
* response size cap (streamed, aborts mid-download) and total timeout.

The guard can be disabled for isolated local testing via
``OUTBOUND_ALLOW_PRIVATE_HOSTS=true`` — never do this on a network-exposed
deployment.
"""

from __future__ import annotations

import ipaddress
import socket
from dataclasses import dataclass
from urllib.parse import urlsplit

import httpx

from app.config import settings

DEFAULT_TIMEOUT = 15.0
DEFAULT_MAX_BYTES = 2_000_000
DEFAULT_MAX_REDIRECTS = 5

# Hostnames that should never be resolved/fetched regardless of DNS.
_BLOCKED_HOST_SUFFIXES = (".local", ".internal", ".localhost")
_BLOCKED_HOSTNAMES = {"localhost"}


class FetchError(Exception):
    """Raised when a fetch is refused (SSRF guard) or fails.

    ``status_code`` is the HTTP status the API layer should surface to the
    caller (``400`` for a rejected/invalid URL, ``502`` for an upstream
    failure, ``413`` when the response exceeds the size cap).
    """

    def __init__(self, message: str, status_code: int = 502) -> None:
        super().__init__(message)
        self.message = message
        self.status_code = status_code


@dataclass
class FetchResult:
    content: bytes
    url: str  # final URL after redirects
    status_code: int
    content_type: str
    encoding: str

    @property
    def text(self) -> str:
        """Best-effort decoded body. Uses the detected/declared charset and
        falls back to UTF-8 with replacement so callers never crash on bytes."""
        try:
            return self.content.decode(self.encoding or "utf-8", errors="replace")
        except (LookupError, ValueError):
            return self.content.decode("utf-8", errors="replace")


def _ip_is_public(ip_str: str) -> bool:
    try:
        ip = ipaddress.ip_address(ip_str)
    except ValueError:
        return False
    return not (
        ip.is_private
        or ip.is_loopback
        or ip.is_link_local
        or ip.is_multicast
        or ip.is_reserved
        or ip.is_unspecified
    )


def _assert_host_allowed(host: str) -> None:
    """Resolve ``host`` and reject it unless every resolved address is public.

    Raises ``FetchError`` (400) when the host is blocked. Bypassed when
    ``outbound_allow_private_hosts`` is set (local testing only).
    """
    if settings.outbound_allow_private_hosts:
        return

    lowered = host.lower().strip("[]")
    if lowered in _BLOCKED_HOSTNAMES or lowered.endswith(_BLOCKED_HOST_SUFFIXES):
        raise FetchError(f"Refusing to fetch blocked host: {host}", 400)

    # A literal IP still has to be public.
    try:
        ipaddress.ip_address(lowered)
        if not _ip_is_public(lowered):
            raise FetchError(f"Refusing to fetch private address: {host}", 400)
        return
    except ValueError:
        pass  # not a literal IP — resolve it below

    try:
        infos = socket.getaddrinfo(lowered, None, proto=socket.IPPROTO_TCP)
    except socket.gaierror as exc:
        raise FetchError(f"Could not resolve host: {host}", 400) from exc

    if not infos:
        raise FetchError(f"Could not resolve host: {host}", 400)

    for info in infos:
        addr = info[4][0]
        if not _ip_is_public(addr):
            raise FetchError(
                f"Refusing to fetch host resolving to non-public address: {host}",
                400,
            )


def _validate_url(url: str) -> str:
    parts = urlsplit(url)
    if parts.scheme not in ("http", "https"):
        raise FetchError(f"Unsupported URL scheme: {parts.scheme or '(none)'}", 400)
    if not parts.hostname:
        raise FetchError("URL has no host", 400)
    _assert_host_allowed(parts.hostname)
    return url


async def safe_get(
    url: str,
    *,
    timeout: float = DEFAULT_TIMEOUT,
    max_bytes: int = DEFAULT_MAX_BYTES,
    max_redirects: int = DEFAULT_MAX_REDIRECTS,
    accept: str | None = None,
    user_agent: str | None = None,
    headers: dict[str, str] | None = None,
    params: dict[str, object] | None = None,
) -> FetchResult:
    """Fetch ``url`` with SSRF protection, redirect re-validation and a size cap.

    Returns a :class:`FetchResult`. Raises :class:`FetchError` on any refusal or
    upstream failure — callers map ``FetchError.status_code`` onto an HTTP error.
    """
    req_headers: dict[str, str] = {
        "User-Agent": user_agent or settings.outbound_user_agent,
    }
    if accept:
        req_headers["Accept"] = accept
    if headers:
        req_headers.update(headers)

    current_url = _validate_url(url)
    first_params = params

    async with httpx.AsyncClient(
        follow_redirects=False, timeout=timeout
    ) as client:
        for _hop in range(max_redirects + 1):
            try:
                async with client.stream(
                    "GET",
                    current_url,
                    headers=req_headers,
                    params=first_params,
                ) as response:
                    first_params = None  # only apply query params to the first hop

                    if response.is_redirect:
                        location = response.headers.get("location")
                        if not location:
                            raise FetchError("Redirect without Location header", 502)
                        current_url = _validate_url(
                            str(response.url.join(location))
                        )
                        continue

                    if response.status_code >= 400:
                        raise FetchError(
                            f"Upstream returned HTTP {response.status_code}",
                            502,
                        )

                    chunks: list[bytes] = []
                    total = 0
                    async for chunk in response.aiter_bytes():
                        total += len(chunk)
                        if total > max_bytes:
                            raise FetchError(
                                f"Response exceeded {max_bytes} byte cap", 413
                            )
                        chunks.append(chunk)

                    return FetchResult(
                        content=b"".join(chunks),
                        url=str(response.url),
                        status_code=response.status_code,
                        content_type=response.headers.get("content-type", ""),
                        encoding=response.charset_encoding or "utf-8",
                    )
            except httpx.HTTPError as exc:
                raise FetchError(f"Fetch failed: {exc}", 502) from exc

    raise FetchError(f"Too many redirects (> {max_redirects})", 502)


async def safe_get_json(url: str, **kwargs: object) -> object:
    """Convenience wrapper: fetch ``url`` and parse the body as JSON.

    Accepts the same keyword arguments as :func:`safe_get`.
    """
    import json

    accept = kwargs.pop("accept", "application/json")
    result = await safe_get(url, accept=accept, **kwargs)  # type: ignore[arg-type]
    try:
        return json.loads(result.text)
    except json.JSONDecodeError as exc:
        raise FetchError(f"Upstream returned invalid JSON: {exc}", 502) from exc
