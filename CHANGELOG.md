# Changelog

All notable changes to **Lukaisu Server** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this
project adheres to [Semantic Versioning](https://semver.org/). Versioning starts
at `0.1.0`.

## [Unreleased]

### Added

* **Local-first by default.** Lukaisu Server ships and serves a bundled web
  client (`dist-app/`, built by `npm run build:app`) as its primary UI under
  `/app/`. The client keeps reading and learning data on-device and works with no
  network at all; the server is optional. Every reading/learning page route (`/`,
  `/texts`, `/text/{id}/read`, `/words`, `/languages`, `/tags`, `/review`, the
  edit forms, print-plain, preferences) is served by the bundle.
* **Optional Python edge** (`services/nlp`) for NLP and network-bound work, run
  standalone with no PHP container. CORS lets the client call it directly, each
  feature group mounts defensively (a missing optional dependency degrades just
  that group instead of failing boot), and `GET /capabilities` reports what an
  instance can do. Includes a standalone `docker-compose.yml` and an `EDGE_ONLY`
  build (outbound edge without the heavy NLP stack).
* **Outbound edge** on the same service: `/content` (Project Gutenberg via
  Gutendex, Global Digital Library, Internet Archive), `/feeds` (RSS/Atom
  parsing), and `/extract` (web article, EPUB by URL or upload, YouTube video info
  + transcript). All outbound fetches go through a single SSRF-protected fetch
  util (`app/services/http/safe_fetch.py`).
* **On-device reading surfaces.** Library, reader, review, languages, tags, and
  text/word editing run against an on-device store, falling back to the server's
  `/api/v1` in same-origin or connected mode. Endpoints that back the bundled
  edit/tags/check pages against a connected server: `GET`/`PUT /texts/{id}`,
  `GET /tags/manage` + `PUT`/`DELETE /tags/{term,text}/{id}`, and
  `POST /texts/check`.
* **First-run UX.** The packaged app opens straight to the on-device library — a
  neutral splash shows while the local DB seeds (no connect-form flash).
  Connecting a server is an optional action under Preferences → Server, which also
  offers "Disconnect" once connected; the section is hidden in same-origin server
  mode. When connecting fails, the connect screen shows actionable CORS help: the
  bundle loads from `https://localhost`, so a self-hosted server must allow that
  origin with `CORS_ALLOWED_ORIGINS=https://localhost` (shown verbatim, copyable).
* **Server-enhanced surfaces** that light up when a server is connected and are
  hidden offline:
  * **Feeds** — a bundled Feeds page mounts the feed-manager SPA against
    `/api/v1/feeds*`; offline it is removed before it can mount and a "connect a
    server" notice is shown instead.
  * **Text importers** — the "Add a text" page imports from a **file or subtitle**
    (read on-device, `.srt`/`.vtt` stripped to plain text — works offline), a
    **web page** (`POST /api/v1/texts/extract-url`), and **YouTube**
    (`GET /api/v1/youtube/video`); the web-page and YouTube panels are gated to a
    connected server, and the imported source URL is saved with the text.
  * **Local dictionaries** — a bundled page lists and deletes a language's local
    dictionaries and does one-click **curated import**
    (`POST /api/v1/local-dictionaries/import-curated`) from a bundled copy of the
    curated registry; arbitrary CSV/JSON/StarDict file upload links out to the
    server's own import form.
  * **EPUB books** — an imported EPUB becomes **one text per chapter**, grouped in
    the library by a book-title tag (no persistent book entity). The "Add a text"
    File panel accepts `.epub` and parses it on-device, so it works offline and
    server-backed.
* **Documentation** under `docs-src/server/`: the local-first overview, the stable
  edge HTTP contract, and design-only specs for multi-device sync and auth.

### Changed

* The PHP backend is positioned as the **legacy full-stack server** for existing
  self-hosters during the transition. New capabilities land in the client
  (TypeScript) or the Python edge rather than in PHP.
