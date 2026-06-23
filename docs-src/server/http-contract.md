# Edge Service HTTP Contract

This is the **stable HTTP interface** of the [Lukaisu Edge Service](../../services/nlp/README.md)
— the optional, Python-first server. The [Lukaisu app](https://github.com/lukaisu/lukaisu)
calls these endpoints directly (CORS is enabled); there is no PHP container in
front of them.

> **Stability.** Treat the request/response shapes below as a contract. The
> client may call any of these endpoints, but it must **never block** on them —
> every capability has an on-device fallback or is "enhanced-when-connected"
> only. Discover what a given instance supports via `GET /capabilities` before
> relying on a group.

- **Base URL**: the edge service root, e.g. `http://localhost:8000` (Docker) or
  whatever URL the user configured. No version prefix.
- **Auth**: none. The edge is stateless and carries no credentials. (Auth only
  enters the picture once multi-device **sync** exists — see
  [auth.md](./auth.md).)
- **Errors**: FastAPI's default `{"detail": "<message>"}` with a conventional
  HTTP status. Outbound failures map as: `400` rejected/invalid URL (incl. SSRF
  refusal), `413` response too large, `422` unprocessable content, `502` upstream
  failure, `503` an optional dependency isn't installed on this instance.

## Service / meta

### `GET /`
Service banner. `{ service, version, docs, capabilities, health }`.

### `GET /health`
Liveness. `{ "status": "ok", "service", "version" }`.

### `GET /capabilities`
Which feature groups loaded on this instance. The client uses this to decide
what to offload.

```json
{
  "service": "Lukaisu Edge Service",
  "version": "0.1.0",
  "capabilities": {
    "parse":     { "available": true,  "prefix": "/parse" },
    "lemmatize": { "available": true,  "prefix": "/lemmatize" },
    "tts":       { "available": true,  "prefix": "/tts" },
    "whisper":   { "available": true,  "prefix": "/whisper" },
    "content":   { "available": true,  "prefix": "/content" },
    "feeds":     { "available": true,  "prefix": "/feeds" },
    "extract":   { "available": true,  "prefix": "/extract" }
  }
}
```
An unavailable group includes a `reason` (the import error). The rest of the
service still works.

---

## NLP

These are the inherently server-side NLP capabilities. The two pure-PHP parsers
(`RegexParser` for space-separated + RTL languages, `CharacterParser` for the
CJK char-by-char fallback) are **ported to TypeScript by the client** and are
*not* served here — only the higher-quality tokenizers are.

### `POST /parse/`
CJK tokenization (MeCab for Japanese, jieba for Chinese).

Request: `{ "text": "...", "parser": "mecab" | "jieba" }`

Response:
```json
{
  "sentences": ["...", "..."],
  "tokens": [ { "text": "...", "is_word": true, "reading": "..."|null }, ... ]
}
```

### `GET /parse/available`
`{ "parsers": [ { "id": "mecab", "name": "...", "languages": ["ja"] }, ... ] }`

### `POST /lemmatize/`
Single-word lemmatization (spaCy).

Request: `{ "word": "running", "language": "en", "lemmatizer": "spacy" }`
Response: `{ "word": "running", "lemma": "run"|null }`

### `POST /lemmatize/batch`
Request: `{ "words": ["...", ...], "language": "en", "lemmatizer": "spacy" }`
Response: `{ "results": { "word": "lemma"|null, ... } }`

### `GET /lemmatize/available`, `GET /lemmatize/languages/{language}`
Capability discovery for spaCy models. (Shapes unchanged from the prior service.)

### `POST /tts/speak`
Request: `{ "text": "...", "voice_id": "en_US-lessac-medium" }`
Response: `audio/wav` bytes.

### `GET /tts/voices`, `GET /tts/voices/installed`, `POST /tts/voices/download`, `DELETE /tts/voices/{voice_id}`
Piper voice management. (Shapes unchanged.)

### `POST /whisper/transcribe` (+ `GET /whisper/status/{job_id}`, `GET /whisper/result/{job_id}`, `DELETE /whisper/job/{job_id}`)
Async transcription. `transcribe` is `multipart/form-data` (`file`, `language?`,
`model`) and returns `{ "job_id", "status" }`; poll `status`, fetch `result`
(`{ text, language, duration_seconds }`). `GET /whisper/available`,
`/whisper/languages`, `/whisper/models` for discovery. (Shapes unchanged.)

---

## Content discovery — `/content`

Cross-origin catalog searches a phone can't make safely. Ports of the PHP
`GutenbergClient` and `GdlClient`, plus a new Internet Archive source.

### `GET /content/sources`
`{ "sources": [ { "id": "gutenberg", "name": "Project Gutenberg", "kind": "text" }, ... ] }`

### `GET /content/gutenberg`
Query: `q` (search; empty ⇒ browse popular), `language` (ISO code), `page` (≥1).

```json
{
  "results": [
    { "id": 1342, "title": "Pride and Prejudice", "authors": ["Austen, Jane"],
      "languages": ["en"], "subjects": ["...", "...", "..."],
      "downloadCount": 117126, "textUrl": "https://www.gutenberg.org/.../1342-0.txt" }
  ],
  "count": 6,
  "next": false
}
```

### `GET /content/gutenberg/text`
Query: `url` (a `textUrl` from a result). Fetches the plain text and strips the
Project Gutenberg header/footer boilerplate. `{ "text": "..." }`.

### `GET /content/gdl`
Query: `q`, `language`, `page`. Page size 20.
```json
{
  "results": [
    { "id": 35879, "title": "...", "publisher": "...", "description": "...",
      "language": "en", "license": "CC-BY-4.0", "level": "Level 4",
      "difficultyTier": "easy"|"medium"|"hard"|"", "thumbnail": "https://...",
      "sourceUri": "https://...", "epubUrl": "https://.../epub-generator/.../14165" }
  ],
  "count": 42,
  "next": true
}
```
Import a GDL book by passing its `epubUrl` to `POST /extract/epub`.

### `GET /content/internet-archive`
Query: `q`, `language`, `page`. Searches the `texts` collection.
```json
{
  "results": [
    { "id": "<identifier>", "title": "...", "authors": ["..."], "languages": ["..."],
      "year": "1899", "textUrl": "https://archive.org/details/<id>",
      "downloadUrl": "https://archive.org/download/<id>" }
  ],
  "count": 123,
  "next": true
}
```

---

## RSS / Atom feeds — `/feeds`

Port of the PHP `RssParser`. Both RSS 2.0 and Atom are normalized.

### `POST /feeds/parse`
Request: `{ "url": "https://...", "article_section": "" }`
```json
{
  "feed_title": "NASA",
  "feed_text": "content"|"description"|"",
  "items": [
    { "title": "...", "link": "https://...", "desc": "... (≤1000 chars)",
      "date": "2026-06-23 16:00:00", "audio": "https://...mp3"|"", "text": "..." }
  ]
}
```
`date` is MySQL `YYYY-MM-DD HH:MM:SS`. `audio` is the first `audio/*` enclosure.

### `GET /feeds/title`
Query: `url`. `{ "title": "..." }` — cheap way to name a subscription.

---

## Extraction — `/extract`

Turn a URL, an uploaded EPUB, or a YouTube video into importable text. Ports of
the PHP `WebPageExtractor`, `EpubParserService`, and `YouTubeApiHandler`.

### `POST /extract/web`
Request: `{ "url": "https://...", "title_hint": "" }`
Response: `{ "title": "...", "text": "...", "sourceUri": "https://..." }`.
`422` when no readable text could be extracted.

### `POST /extract/epub`
Request: `{ "url": "https://....epub" }` (e.g. a GDL `epubUrl`).
```json
{
  "metadata": { "title": "...", "author": "..."|null, "description": "..."|null,
                "language": "..."|null, "sourceUri": "https://..." },
  "chapters": [ { "num": 1, "title": "...", "content": "..." }, ... ],
  "text": "all chapters joined"
}
```
`422` when the book has too little readable text (likely image-only).

### `POST /extract/epub/upload`
`multipart/form-data` with `file`. Same response as `/extract/epub`. Rejects
non-ZIP uploads with `400`.

### `GET /extract/youtube/configured`
`{ "configured": true|false }` — whether a `YT_API_KEY` is set on this instance.

### `GET /extract/youtube/video`
Query: `video_id`. Requires `YT_API_KEY`.
`{ "title": "...", "description": "...", "source_url": "https://youtube.com/watch?v=..." }`.

### `GET /extract/youtube/transcript`
Query: `video_id`, `language?`. No API key needed.
```json
{
  "video_id": "...", "language": "en", "text": "full transcript",
  "segments": [ { "start": 0.0, "duration": 3.2, "text": "..." }, ... ]
}
```
`404` when no transcript is available.

---

## Mapping from the legacy PHP API

For self-hosters running the legacy full-stack PHP server during the transition,
these edge endpoints correspond to the old `/api/v1` routes as follows. The PHP
routes still work today; they proxy (NLP) or reimplement (outbound) the same
behavior. The direction is for the client to call the edge directly.

| Legacy PHP `/api/v1` | Edge endpoint |
|---|---|
| `POST /tts/speak`, `/tts/voices*` | `POST /tts/speak`, `/tts/voices*` (proxied to this service already) |
| `GET /whisper/*` | `/whisper/*` |
| `GET /texts/gutenberg-suggestions`, `/texts/library-search` | `GET /content/gutenberg` |
| `GET /texts/library-preview` | _client-side_ (coverage uses the on-device vocabulary) |
| `GET /texts/gdl-search` | `GET /content/gdl` |
| `POST /texts/extract-epub-url` | `POST /extract/epub` |
| `POST /texts/extract-url` | `POST /extract/web` |
| `GET /youtube/configured`, `/youtube/video` | `GET /extract/youtube/configured`, `/extract/youtube/video` |
| `GET /feeds/articles` (fetch/parse part) | `POST /feeds/parse` |
| _(new)_ | `GET /content/internet-archive`, `GET /extract/youtube/transcript` |

Everything else in the legacy `/api/v1` surface (languages, texts CRUD, terms,
review, tags, settings, activity) is **data/CRUD** and moves to the **client's
on-device database**, not to this service. See
[local-first.md](./local-first.md).
