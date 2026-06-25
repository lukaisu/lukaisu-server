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
