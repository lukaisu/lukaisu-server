# Lukaisu Edge Service

The **Lukaisu Edge Service** is the nucleus of the optional, Python-first
Lukaisu server. It is a [FastAPI](https://fastapi.tiangolo.com/) application that
does the work a phone **cannot** do locally:

| Group | Endpoints | Notes |
|---|---|---|
| **NLP** | `/parse`, `/lemmatize`, `/tts`, `/whisper` | CJK tokenization (MeCab/jieba), lemmatization (spaCy), TTS (Piper), transcription (Whisper) |
| **Outbound / network** | `/content`, `/feeds`, `/extract` | Content discovery (Gutendex, GDL, Internet Archive), RSS, web/EPUB/YouTube extraction |

It runs **standalone** — no PHP container and no database — and the
[Lukaisu app](https://github.com/lukaisu/lukaisu) calls it directly over HTTP.
This is the deployment a user opts into when they want server-enhanced features;
the no-server reading/learning path lives entirely on-device.

See [`docs-src/server/http-contract.md`](../../docs-src/server/http-contract.md)
for the stable request/response contract of every endpoint.

## Why "edge"?

In the [local-first migration](../../docs-src/server/local-first.md), data and
rendering move to the client. What remains on the server is the network- and
compute-bound **edge**: the few capabilities that genuinely can't run on a
phone. This service is that edge.

## Running it

### Docker (recommended)

```bash
cd services/nlp

# Full image — NLP + outbound edge (large: pulls spaCy, Whisper, MeCab, ffmpeg)
docker compose up --build

# Lightweight image — outbound edge only, no heavy NLP stack
EDGE_ONLY=1 docker compose up --build
```

Then:

```bash
curl http://localhost:8000/health
curl http://localhost:8000/capabilities   # what this instance can actually do
open http://localhost:8000/docs           # interactive OpenAPI docs
```

### Local Python (development)

```bash
cd services/nlp
python -m venv .venv && source .venv/bin/activate

# Everything:
pip install -r requirements.txt
# ...or just the outbound edge (no spaCy/Whisper/Piper/MeCab):
pip install -r requirements-edge.txt

uvicorn app.main:app --reload --port 8000
```

## Graceful degradation

Every feature group is mounted **defensively** in `app/main.py`. If an optional
dependency is missing (e.g. MeCab isn't installed in an `EDGE_ONLY` build), that
router is skipped and the rest of the service still boots. `GET /capabilities`
reports exactly which groups loaded:

```json
{
  "service": "Lukaisu Edge Service",
  "version": "0.1.0",
  "capabilities": {
    "parse":     { "available": false, "prefix": "/parse", "reason": "ModuleNotFoundError: No module named 'MeCab'" },
    "content":   { "available": true,  "prefix": "/content" },
    "feeds":     { "available": true,  "prefix": "/feeds" },
    "extract":   { "available": true,  "prefix": "/extract" }
  }
}
```

The client reads `/capabilities` to decide what to offload to a connected server
versus handle on-device. **The client must never block on the server**: if a
capability is unavailable (or no server is configured at all), it falls back to
the on-device path (e.g. character-by-character CJK parsing).

## Configuration

All settings are environment variables (see `app/config.py`):

| Variable | Default | Purpose |
|---|---|---|
| `CORS_ALLOWED_ORIGINS` | `*` | Comma-separated allowed origins. `*` is safe here — the service is stateless and sends no credentials. Tighten in shared-origin deployments. |
| `PIPER_VOICES_DIR` | `/app/voices` | Where downloaded Piper TTS voices are stored. |
| `OUTBOUND_ALLOW_PRIVATE_HOSTS` | `false` | **SSRF guard.** Keep `false` in any network-exposed deployment. Only `true` for isolated local testing against a fixture server. |
| `YT_API_KEY` | _(empty)_ | Optional. Enables `GET /extract/youtube/video` (YouTube Data API v3). Transcripts do not need a key. |

## Security: SSRF protection

Every outbound fetch (content, feeds, extraction) goes through
`app/services/http/safe_fetch.py`, the Python port of the PHP
`UrlUtilities::safeHttpGet` guard. It enforces an http/https scheme allow-list,
re-validates **every** redirect hop, blocks private/loopback/link-local/reserved/
multicast addresses, and caps response size and total time. Never call `httpx`
directly from a router — always go through `safe_get` / `safe_get_json`.
