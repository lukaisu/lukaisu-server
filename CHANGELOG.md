# Changelog

All notable changes to **Lukaisu Server** are documented in this file.

Lukaisu Server is a server fork derived from
[Learning with Texts (LWT)](https://github.com/HugoFara/lwt) at version 3.2.0.
The change history prior to `0.1.0` lives in the upstream LWT changelog.

The format is based on [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

* **Local-first migration — server side.** Began turning the PHP backend into an
  optional, Python-first edge whose only jobs are NLP and network-bound work.
* The Python `services/nlp` service now **runs standalone** (no PHP container):
  CORS is enabled so the client can call it directly, every feature group is
  mounted defensively (a missing optional dependency degrades that one group
  instead of failing the boot), and a new `GET /capabilities` endpoint reports
  what a given instance can do. A standalone `docker-compose.yml` and an
  `EDGE_ONLY` build (outbound edge without the heavy NLP stack) were added.
* **Outbound edge** ported to Python on the same service:
  * `/content` — Project Gutenberg (Gutendex), Global Digital Library, and a new
    Internet Archive source.
  * `/feeds` — RSS/Atom parsing.
  * `/extract` — web-article, EPUB (URL + upload), and YouTube (video info +
    transcript) extraction.
  * All outbound fetches go through a single SSRF-protected fetch util
    (`app/services/http/safe_fetch.py`), a port of the PHP
    `UrlUtilities::safeHttpGet` guard.
* Documentation under `docs-src/server/`: the local-first overview, the stable
  edge HTTP contract, and design-only specs for multi-device sync and auth.
* **PHP-view cut-over (in-repo).** The server now serves the bundled client
  (`dist-app/`, built by `npm run build:app` and shipped in the image) as its own
  browser UI under `/app/`, talking to its own `/api/v1` in same-origin
  server-backed mode. Every reading/learning page route (`/`, `/texts`,
  `/text/{id}/read`, `/words`, `/languages`, `/tags`, `/review`, the edit forms,
  print-plain, preferences) now redirects into the bundle; the bundle is the
  default UI for those surfaces. Data routes (POST/JSON/DELETE), annotated print,
  feeds/books, admin/auth, and the API are unaffected. *Note: requires a live
  smoke-test on a real instance before relying on it (see
  `docs-src/server/php-view-retirement.md`).*
* New `/api/v1` endpoints so the bundled edit/tags/check pages work against a
  connected server (not just offline): `GET`/`PUT /texts/{id}` (single-text edit),
  `GET /tags/manage` + `PUT`/`DELETE /tags/{term,text}/{id}` (tag management), and
  `POST /texts/check` (parse preview). These bring the server's `/api/v1` to
  parity with the bundle's needs for the cut-over (a deliberate exception to the
  PHP-frozen policy, scoped to enabling it).
* **Bundled client first-run UX (local-first).** The packaged app now opens to
  the on-device library instead of a server picker: the launch page shows a
  neutral splash while the local DB seeds (no connect-form flash), then redirects
  to the library. Connecting a server is demoted to an optional action under
  Preferences → Server ("Connect a server"), which also offers "Disconnect" once
  connected; the section is hidden in same-origin server mode.
* **CORS connect-onboarding (client).** When connecting a server fails on the
  server step, the connect screen now shows an actionable help block: the bundle
  loads from `https://localhost`, so a self-hosted server must allow that origin
  with `CORS_ALLOWED_ORIGINS=https://localhost` (shown verbatim, copyable). A
  failed `fetch` can't distinguish a CORS rejection from an unreachable host, so
  the copy covers both. `.env.example` now documents the app's origin. The
  server already honours `CORS_ALLOWED_ORIGINS` (`Cors.php`) — no server change.
* **Bundled Feeds page (server-enhanced, Job B surface 1).** The packaged client
  now has a Feeds page (`feeds.html`) that mounts the existing feed-manager SPA.
  It is **gated**: with a server connected (server-backed or same-origin) the SPA
  loads and manages feeds against `/api/v1/feeds*`; **offline (local-first) it is
  removed before it can mount** — so its calls never fall through to an absent
  network — and a "connect a server" notice is shown instead. RSS/Atom fetching
  needs a server's egress, so feeds stays enhanced-when-connected (no on-device
  feed store yet). The languages page's `/feeds?…` links now resolve to this
  bundled page. E2E covers both states (gated-offline + mounts-connected). The
  feed wizard and per-feed edit routes remain server-rendered (fall through when
  connected).
* **Bundled text importers (server-enhanced, Job B surface 2 — first slice).** The
  packaged "Add a text" page (`text.html`) gained import panels that fill the
  Title/Text fields, after which the normal create path lands the text on-device:
  **File / subtitle** (read on-device, `.srt`/`.vtt` stripped to plain text — works
  **offline**), **Web page** (`POST /api/v1/texts/extract-url`), and **YouTube**
  (`GET /api/v1/youtube/video`). The web-page and YouTube panels go through the
  **api client** (so they reach the *connected* server — the legacy components used
  raw relative `fetch` and only worked same-origin) and are **gated**: hidden
  offline, shown when a server is connected. The imported source URL is saved with
  the text. Whisper audio transcription is deferred to a follow-up. E2E covers both
  states (file-on-device + gated offline; web-page + YouTube fill the form
  connected).
* **Bundled local-dictionaries page (server-enhanced, Job B surface 3).** A new
  gated `dictionaries.html` manages a language's local dictionaries against a
  connected server: **list** + **delete**, and **one-click curated import**
  (`POST /api/v1/local-dictionaries/import-curated`) from a bundled copy of the
  curated registry — the only import path a bearer-auth remote client can use
  (file upload needs a server-side path). Arbitrary CSV/JSON/StarDict **file
  upload links out** to the server's own web import form. Offline (local-first) the
  page shows a "connect a server" notice and the reader keeps its online-dictionary
  lookups (no on-device dictionary store). Reached from a gated "Manage local
  dictionaries" link on the language-edit page. E2E covers gated-offline +
  connected (list / curated import / delete).
* **EPUB books → per-chapter texts (server-enhanced, Job B surface 4).** Settled the
  on-device book data model as **Option A**: an imported EPUB becomes **one text per
  chapter**, grouped in the library by a tag (the book title), with no persistent
  book entity. The on-device EPUB parser now exposes its chapters (previously joined
  + discarded); `importEpubText` (GDL / URL) creates per-chapter texts and opens
  chapter 1. The "Add a text" page's File panel now accepts **`.epub`** and imports
  it **on-device** (parsed in the browser) as per-chapter texts — works offline and
  server-backed. The reader treats chapters as standalone texts (offline chapter-nav
  + reading-progress remain a deferred Option-B upgrade); the server's PHP book pages
  are unaffected and still serve server-backed users.

### Changed

* Policy: **PHP is frozen** — no new PHP features. New capabilities land in the
  client (TypeScript) or the Python edge. PHP remains runnable as the legacy
  full-stack server for existing self-hosters during the transition.

## [0.1.0] - 2026-06-23

### Added

* Initial release of Lukaisu Server — the self-hosted, reading-based
  language-learning backend that powers the
  [Lukaisu](https://github.com/lukaisu/lukaisu) app. Forked from LWT 3.2.0 as a
  clean-cut starting point, with the project rebranded from LWT to Lukaisu Server
  throughout (PHP namespace, package identity, runtime keys, assets, and docs).
  No functional changes versus the LWT 3.2.0 baseline.
