"""
Lukaisu edge service — FastAPI application entry point.

This is the nucleus of the optional, Python-first Lukaisu server. It does the
work a phone cannot do locally:

* **NLP**: CJK tokenization (MeCab/jieba), lemmatization (spaCy), TTS (Piper),
  transcription (Whisper).
* **Outbound / network**: content discovery (Gutendex, GDL, Internet Archive),
  RSS feeds, and web/EPUB/YouTube extraction.

It runs **standalone** — no PHP container, no database — and the client calls it
directly over HTTP (CORS is enabled). Every feature group is mounted
defensively: if an optional dependency is missing (e.g. MeCab isn't installed),
that router is skipped and the rest of the service still boots. Query
``/capabilities`` to discover what a connected instance can actually do.
"""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.config import settings

SERVICE_NAME = "Lukaisu Edge Service"
SERVICE_VERSION = "0.1.0"

app = FastAPI(title=SERVICE_NAME, version=SERVICE_VERSION)

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origins_list,
    allow_credentials=False,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Each entry: (capability id, module path, url prefix, tag). Routers are mounted
# in a try/except so a missing optional dependency degrades that one feature
# instead of taking down the whole service. ``_capabilities`` records the result
# for the /capabilities endpoint.
_ROUTER_SPECS = [
    ("parse", "app.routers.parse", "/parse", "Parsing"),
    ("lemmatize", "app.routers.lemmatize", "/lemmatize", "Lemmatization"),
    ("tts", "app.routers.tts", "/tts", "TTS"),
    ("whisper", "app.routers.whisper", "/whisper", "Whisper"),
    ("content", "app.routers.content", "/content", "Content discovery"),
    ("feeds", "app.routers.feeds", "/feeds", "RSS feeds"),
    ("extract", "app.routers.extract", "/extract", "Extraction"),
]

_capabilities: dict[str, dict[str, object]] = {}


def _mount_routers() -> None:
    import importlib

    for cap_id, module_path, prefix, tag in _ROUTER_SPECS:
        try:
            module = importlib.import_module(module_path)
            app.include_router(module.router, prefix=prefix, tags=[tag])
            _capabilities[cap_id] = {"available": True, "prefix": prefix}
        except Exception as exc:  # noqa: BLE001 - degrade, don't crash the boot
            _capabilities[cap_id] = {
                "available": False,
                "prefix": prefix,
                "reason": f"{type(exc).__name__}: {exc}",
            }


_mount_routers()


@app.get("/")
async def root():
    """Service banner — confirms the edge is reachable without the PHP app."""
    return {
        "service": SERVICE_NAME,
        "version": SERVICE_VERSION,
        "docs": "/docs",
        "capabilities": "/capabilities",
        "health": "/health",
    }


@app.get("/health")
async def health():
    return {"status": "ok", "service": SERVICE_NAME, "version": SERVICE_VERSION}


@app.get("/capabilities")
async def capabilities():
    """Report which feature groups loaded successfully on this instance.

    The client uses this to decide what to offload to a connected server vs.
    handle on-device. A feature with ``available: false`` means its optional
    dependency is not installed here (the rest of the service still works).
    """
    return {
        "service": SERVICE_NAME,
        "version": SERVICE_VERSION,
        "capabilities": _capabilities,
    }
