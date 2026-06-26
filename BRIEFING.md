# Lukaisu Server — Local-First Migration Briefing

> **Audience:** the implementation agent working in this repo (`lukaisu-server`).
> **Paired doc:** `lukaisu/BRIEFING.md` (the client). Read the shared sections
> (Goal, Seam) — they are identical in both so the two tracks compose.

## Mission

Turn this PHP backend (a rebranded fork of Learning with Texts) into an
**optional, Python-first server** whose only jobs are **NLP and
network-bound work** (and, later, **multi-device sync**). The PHP backend is
scaffolding to be progressively hollowed out: **rendering** moves to the client,
**data/DB** moves to the client, and what remains on the server is what genuinely
cannot run on a phone.

**Do not add new PHP features. Freeze PHP; build Python.**

## The shared goal (this is the milestone)

**A Lukaisu app installed from F-Droid must work with NO server.** A first-time
user installs the app, and — fully offline — can create a language, paste/import
a text, read it with word-status highlighting, save words, and review them.

That milestone is **mostly client-side work** (see `lukaisu/BRIEFING.md`). Your
job on the server side is to make sure the server is **optional, not required**,
and that when a server *is* connected it is the **Python** one doing the few
things the client can't: CJK tokenization, lemmatization, TTS, transcription,
and outbound content fetches.

Concretely, "server optional" means: **nothing on the critical read/learn path
for space-separated and right-to-left languages may require a server.** CJK
(Japanese/Chinese), content discovery, TTS, and transcription may be
"enhanced-when-connected" — they are *not* part of the no-server milestone.

## The client⇄server seam (shared contract — identical in both briefs)

Classify every capability into one of four buckets. The split below is the
contract both agents build to.

| Bucket | Owner after migration | Examples |
|---|---|---|
| **Rendering** | **Client** (already TS) | reader, review surface, word popups, navbar, i18n — already in `src/frontend/`, bundled into the app (see *Rendering hollow-out*) |
| **Data / DB** | **Client** (on-device DB) | languages, texts, words/terms, sentences, word-occurrences, tags, settings, review scheduling |
| **NLP** | **Optional server (Python)** | CJK parse (MeCab/jieba), lemmatization (spaCy), TTS (Piper), Whisper transcription |
| **Outbound / network** | **Hybrid (revised 2026-06-26)** — see below | structured catalog *browse* on the **client**; everything else **optional server (Python)** |

**Outbound split (2026-06-26).** The original seam put *all* outbound work on the
optional server because "a phone can't make arbitrary cross-origin requests
safely." That holds for arbitrary URLs, but not for the structured, fixed-host
catalogs: the bundled app runs in a Capacitor WebView with `CapacitorHttp`, which
is CORS-free, so low-SSRF-risk catalog browse can run client-side. The maintainer
chose the **Hybrid** option, so this bucket now splits:

- **Client (CORS-free via `CapacitorHttp`):** Gutendex (Project Gutenberg) and
  Global Digital Library browse/search, difficulty tiers + reader-level computed
  against on-device vocabulary, Gutenberg **plain-text** import (fetch → strip
  boilerplate → parse on-device), **GDL EPUB** import (download → unzip via
  fflate → walk the OPF spine → HTML→text → parse on-device, `content/epub.ts`),
  and the **coverage preview** for both Gutenberg (plain-text) and GDL (EPUB)
  books (sample the book + measure it against the on-device vocabulary; the GDL
  variant downloads + parses the EPUB, so it is local-first only). Lives in
  `src/frontend/js/shared/offline/local/content/` + `repositories/content.ts`,
  wired through `routeLocal` and surfaced behind the home "Discover books" toggle.
- **Optional server (Python), unchanged:** Internet Archive, RSS feeds, YouTube
  transcripts, and **arbitrary web-URL** extraction (incl. coverage preview for
  non-Gutenberg URLs). These keep the SSRF guard and stay
  "enhanced-when-connected." The server also keeps its own **EPUB** upload/URL
  import flow for its web UI; only the *catalog* EPUB path (GDL) now runs
  client-side. The Python `content`/`feeds`/`extract` routers remain the
  implementation for the server's own UI and for these.

Two parsers are **pure PHP today and must be ported to TS by the client agent**
so they run with no server: `RegexParser` (space-separated + RTL languages) and
`CharacterParser` (CJK fallback, char-by-char). Everything else NLP is genuinely
server-side. See `src/Modules/Language/Infrastructure/Parser/` — that PHP is the
reference implementation the client will port.

**Degradation rule:** with no server, CJK languages fall back to the
character-by-character parser (functional, lower quality). When a server is
connected, CJK uses the Python tokenizer. The client must never *block* on the
server.

## Your scope (this repo)

The end state is a small Python service. Get there in this order:

> **Status (2026-06).**
> - **Step 1 — DONE.** The edge service stands alone (`services/nlp/`, FastAPI):
>   `parse` / `lemmatize` / `tts` / `whisper` routers, callable without the PHP
>   container, Docker-verified (full **and** `EDGE_ONLY` images). HTTP contract
>   written up in `docs-src/server/http-contract.md`.
> - **Step 2 — DONE.** Outbound integrations ported to the same service
>   (`content` / `feeds` / `extract` routers: Gutendex, Global Digital Library,
>   Internet Archive, RSS, YouTube, web/EPUB), all behind the SSRF guard
>   (`services/nlp/app/services/http/safe_fetch.py`).
> - **Step 3 — DESIGNED, not built** (`docs-src/server/sync-contract.md`). Out of
>   the milestone by design.
> - **Step 4 — DESIGNED, not built** (`docs-src/server/auth.md`). Gated on sync.
> - **Step 5 — standing policy.** PHP is frozen; no new features added.
>
> **The F-Droid milestone is met, proven, and device-QA'd (2026-06-26).** The
> bundled app does the whole create-language → paste-text → read → save → review
> flow fully offline, making **zero `/api/v1` calls** — asserted
> (`apiAttempts === 0`) by the browser E2E in `cypress/app-e2e/` — and the full
> offline slice has now been QA'd on physical hardware (Pixel 8a). The client-side
> first-run flip shipped too: the packaged app **opens to the local library** (a
> neutral launch splash covers the first-run seed, then redirects — no connect-form
> flash), **"connect a server" is an optional Preferences → Server action** (with
> "Disconnect"), and a failed server-step connect **surfaces the CORS requirement**
> (`CORS_ALLOWED_ORIGINS=https://localhost`; the server already honours it via
> `src/Shared/Infrastructure/Http/Cors.php` — no server change). The create surfaces
> are bundled as `src/frontend/app/{language,text}.html` (purpose-built,
> API-client-driven — the server-rendered forms do native POSTs and can't run
> offline). CJK still falls back to the on-device character parser when no server is
> connected, per the degradation rule.
>
> **Rendering hollow-out underway (Job A).** The first management pages are now
> bundled: the **terms list** (`src/frontend/app/words.html`, prerendered from the
> PHP view by `build/prerender-app-view.php`), the **term edit form**
> (`word.html`, a purpose-built API form) — list, inline-edit, full-edit and delete
> terms fully offline (asserted `apiAttempts === 0`; closing this needed a new
> on-device `GET /terms/{id}`) — the **languages list** (`languages.html`,
> prerendered; list / set-current / reparse / delete on-device, zero new
> data-layer work) — and the **language settings form** (`language-edit.html`, a
> purpose-built API form reached from the list's Edit links; load + save offline
> via `GET`/`PUT /languages/{id}`, zero new data-layer work) — and the **archived
> texts** page (`texts.html`, prerendered from `archived_list.php`; the *active*
> manage list was already bundled as `library.html`, which mounts the same
> `textsGroupedApp`). Page 5 closed a real data-layer gap: the grouped archived
> view's `GET /languages/with-archived-texts` plus single-text
> `POST /texts/{id}/archive`·`/unarchive` and `DELETE /texts/{id}` are now served
> on-device (the components route through them only when local-first, leaving the
> server PWA's web-route path untouched) — and the **text edit form**
> (`text-edit.html`, a purpose-built API form; one page serves both the active and
> archived Edit links, picking its redirect from the loaded record). Page 6 closed
> another data-layer gap: local `GET`/`PUT /texts/{id}` (`getText`/`updateText`)
> now load + save a single text on-device, **re-parsing** the body when it changed
> so the reader reflects the edit — the PHP server has these only as a web-route
> form, so (like page 5's per-text arms) they are local-first only — and the
> **tags management page** (`tags.html`, a purpose-built API form; the legacy
> `tag_list.ts` is native-nav, not mountable). Page 7 closed a third data-layer
> gap: the server's `/api/v1` tags surface is GET-only, so local
> `GET /tags/manage` + `PUT`/`DELETE /tags/{term,text}/{id}` (rename/delete) are
> now served on-device — tag *creation* stays implicit (tagging a term/text), the
> useful management ops being rename + delete. "New term"
> stays server-only (no offline create contract); the standalone language *wizard*
> is not separately bundled (the "add a language" page's presets already serve it);
> text importers (file / URL / Gutenberg / GDL / transcription) stay server-side
> (Job B). The PHP views are **kept** — they still back the server's own PWA — and
> are deleted at the cut-over, not now. Plan + per-view checklist:
> `docs-src/server/php-view-retirement.md`.

1. **Make the NLP service stand alone.** The Python NLP service (the `nlp`
   Docker service — verify its exact location; the Dockerfile copies an `app/`,
   likely under `services/nlp/`) already exposes parse / tts / whisper /
   lemmatize over HTTP and is the nucleus of the future server. Confirm it runs
   and is callable **without the PHP container** in front of it, and document its
   HTTP contract (request/response per endpoint) as the stable interface the
   client will optionally call. PHP currently proxies it via
   `src/Modules/Language/Infrastructure/NlpServiceHandler.php` — that file is
   your inventory of what the client needs.

2. **Stand up the Python edge for outbound work.** Port the network-bound
   integrations (Gutendex, GDL, Internet Archive, RSS, YouTube, web/EPUB URL
   extraction) to Python endpoints on the same service, or stub the contract for
   them. These inherently need a server (a phone can't make arbitrary
   cross-origin requests safely), so they define the "enhanced-when-connected"
   surface. PHP reference: `src/Shared/Infrastructure/Http/*Client.php`,
   `src/Modules/Feed/Application/Services/RssParser.php`,
   `src/Modules/Book/Application/Services/EpubParserService.php`.

3. **Define the sync contract (design first, implement later).** When a user
   opts into a server, the client's on-device DB syncs. Specify the data model
   and a conflict strategy **before** writing sync code. The DB schema to mirror
   is the per-user-scoped relational model in `db/schema/` and `db/migrations/`
   (tables: `languages`, `texts`, `words`, `sentences`, `word_occurrences`,
   `tags`/maps, `settings`, `news_feeds`/`feed_links`, `activity_log`; user
   scoping via `*UsID` columns; word status 1–5/98/99). **Sync is the hard part
   — see Risks. It is NOT in the F-Droid milestone; design only.**

4. **Auth, only where sync needs it.** Local-first single-device use needs **no
   auth**. Auth (bearer tokens, register/login, recovery code, ALTCHA) only
   matters once a server stores multiple users' synced data. Keep the existing
   model as the reference; reimplement minimally in Python when sync lands. PHP
   reference: `src/Modules/User/Http/UserApiHandler.php`.

5. **Freeze and shrink PHP.** Don't extend it. As client + Python take over
   buckets, the PHP surface only shrinks. Keep it runnable as the legacy
   full-stack server for existing self-hosters during the transition.

## Rendering hollow-out (relocating the frontend)

The seam puts **rendering on the client**, but today `src/frontend/` (the TS
reader/library/review/connect UI, the on-device DB, and the parsers) physically
lives in this repo and is built **two ways**:

- `vite.config.ts` → `dist/` — **this server's own browser UI** (the PHP-served
  PWA).
- `vite.app.config.ts` → `dist-app/` — the **bundled app** the `lukaisu` repo
  packages into the APK.

One frontend source, **two consumers** — that shared ownership is the only thing
keeping the frontend in this repo. Untangle it in two pieces:

**Piece 1 — sever the PHP→HTML build coupling. DONE (2026-06).** The app pages
(`src/frontend/app/{index,library,read,review}.html`) used to be prerendered from
this server's PHP views at build time: a `<!--LUKAISU_VIEW:…-->` marker expanded
by `build/php-view-prerender.mjs` (a mini PHP→HTML transpiler), pulling view
partials and `locale/en/*.json`. That output is now **committed as static HTML**;
the prerender Vite plugin and `build/php-view-prerender.mjs` are deleted.
`build:app` no longer reads any PHP view, partial, or locale file — verified
byte-identical `dist-app/` before/after, typecheck green. The app frontend builds
from `src/frontend/` alone now. **Accepted trade-off:** those pages no longer
auto-track the PHP templates — the app owns them and may diverge (correct, since
the app is independent and PHP is frozen).

**Piece 2 — relocate the frontend to `lukaisu` (NOT done; this is your call).**
The shared JS/CSS (`src/frontend/js`, `src/frontend/css`, ~2 MB, **self-contained
— no import escapes `src/frontend/`**) should become owned by the `lukaisu` app.
That removes `lukaisu`'s build-time dependency on this repo entirely and kills the
F-Droid submodule question (see `lukaisu/FDROID.md` Step 5). The blocker is the
**second consumer** — this server still builds its own browser UI from the same
source. Resolve it one of three ways:

  1. **Retire this server's browser UI** (recommended, mission-aligned: server →
     headless Python NLP/outbound). Once nothing here serves a PWA, move
     `src/frontend/` to `lukaisu` wholesale and delete `vite.config.ts`'s web-UI
     build. Cleanest end state, but **gated on PHP/PWA actually being
     decommissioned** — which is out of scope for the F-Droid milestone (PHP
     stays as the legacy full-stack server during the transition).
  2. **Extract to a shared package** (`@lukaisu/frontend`) both repos consume.
     Keeps a server UI; costs a publish/versioning pipeline in this repo.
  3. **Reverse the dependency** (server pulls the frontend from `lukaisu`).
     Avoid — it just moves the cross-repo coupling here, and risks a cycle (this
     repo's PHP views were the app's template source until Piece 1).

**Recommendation:** don't do Piece 2 in isolation — sequence it with retiring the
server browser UI (option 1). Until then, `lukaisu` bundling the frontend from
here (a git submodule for the F-Droid catalog build) is the correct interim.
Coordinate the move with the `lukaisu` agent; never fork the frontend into two
diverging copies.

## What to reuse / where things are

- **API surface to mirror:** `src/backend/Api/V1/Endpoints.php` (the registry)
  and `src/backend/Api/V1/ApiV1.php` (router + auth). This is the full list of
  what clients call today.
- **NLP proxy (what the client needs from Python):**
  `src/Modules/Language/Infrastructure/NlpServiceHandler.php`.
- **Parsers (pure-PHP, ported by the client):**
  `src/Modules/Language/Infrastructure/Parser/` (`RegexParser`,
  `CharacterParser`, `MecabParser`, `ParserRegistry`) and
  `src/Modules/Language/Application/` text-parsing orchestration.
- **DB schema for the sync model:** `db/schema/`, `db/migrations/`.
- **Server-side design docs:** `docs-src/server/` — `http-contract.md` (the
  optional NLP/outbound HTTP surface), `local-first.md` (the offline
  architecture and four-bucket seam), `sync-contract.md` (the design-only sync
  model and conflict strategy), `auth.md` (the design-only auth model),
  `php-view-retirement.md` (the plan + per-view checklist for moving rendering
  off PHP — *Rendering hollow-out* made concrete).

## Out of scope (for the F-Droid milestone)

- Implementing sync (design the contract only).
- Multi-user auth on the Python server (until sync exists).
- Rewriting CRUD in Python (CRUD moves to the *client*, not to Python).
- Decommissioning PHP (it stays as the legacy server during transition).

## Open decisions & risks

- **Sync is the underestimated monster.** Decide last-write-wins vs CRDT vs
  server-authoritative-when-online *before* building it. Per-row timestamps +
  tombstones + a pending-op queue on the client is the minimum. Spike this in
  isolation.
- **Where does the frontend TS ultimately live?** See **Rendering hollow-out**
  above. Piece 1 (severing the PHP→HTML build coupling) is **done**; Piece 2
  (relocating `src/frontend/` to the `lukaisu` app, gated on retiring this
  server's browser UI) is the remaining call. For the milestone it's fine to keep
  the TS here and bundle it; the long-term home is the `lukaisu` app. Never fork
  the frontend into two diverging copies.
- **Python framework:** the NLP service appears to be FastAPI already — extend
  it rather than introducing a second framework.

---
*Paired with `lukaisu/BRIEFING.md`. Keep the shared "Goal" and "Seam" sections in
sync if either changes.*
