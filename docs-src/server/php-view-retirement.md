# PHP View Retirement — plan & checklist

> **Goal:** *no more PHP views.* Move the user-facing rendering off the PHP
> server and onto the bundled client, so the PHP `View` layer can be deleted and
> the server reduced to `/api/v1` (+ optional Python NLP/outbound) — the
> hollow-out from `BRIEFING.md` → *Rendering hollow-out*.
>
> **Paired docs:** `local-first.md` (the four-bucket seam), `BRIEFING.md`
> (mission), `lukaisu/BRIEFING.md` (the client side).
>
> **Status:** plan written 2026-06-25. The read/learn loop is already bundled
> (`read`/`review`/`library`/connect + minimal create). **All Job-A pages (1–11)
> landed 2026-06-25:** the terms list (`words.html`), the term **edit** form
> (`word.html`), the languages list (`languages.html`), the language
> **settings** form (`language-edit.html`), the **archived texts** page
> (`texts.html`), the **text edit** form (`text-edit.html`), the **tags**
> management page (`tags.html`), the **preferences** page (`settings.html`), the
> **parse-preview** tool (`text-check.html`), the **home dashboard**
> (`home.html`), and the **plain-print** page (`text-print.html`). Pages 5–8 were
> **the critical path to the cut-over**; pages 9–11 were the *optional* Job-A
> pages (print is plain-only offline — the Improved Annotated Text is
> server-only). **Job A is complete, and the in-repo cut-over has landed
> (2026-06-25):** the PHP server now serves the bundle under `/app/` as its
> default UI for all Job-A surfaces, server-backed via `/api/v1` (3 new endpoints
> closed the last gaps). `ViteHelper`/the `vite.config` web build are retained
> (Job B/C still need them), the `src/frontend` → `lukaisu` move is deferred, and
> the dormant Job-A views are not yet deleted — all pending live verification (see
> *The cut-over* below). (Page 2's "new term" and page 4's standalone wizard
> halves are deferred — see their table notes; page 5's *active* manage half was
> already bundled as `library.html` — see its subsection.)

## The shape of the problem

There are **93 PHP view files** across 11 modules (`src/Modules/*/Views/`),
reached by the routes in `src/Shared/Infrastructure/Routing/routes.php`. The
bundled client currently ships **6 pages** (`app/{index,library,read,review,language,text}.html`).

"Retire the PHP views" is **not one job** — it splits three ways:

| Job | What it is | Gated on |
|---|---|---|
| **A — Port the reading/learning UI** | Build the remaining reader/management pages as bundle entries; delete the PHP views they replace. *This is the real hollow-out.* | nothing — do it now |
| **B — Server-enhanced surfaces** | Feeds, books/EPUB, dictionary import. Outbound bucket → only live when a server is connected. Become client pages hidden offline. | server-optional UX |
| **C — Admin / auth / profile** | Administering the PHP server + multi-user auth. *Never client rendering.* Dies **with** the PHP server. | PHP decommission (post-sync) |

The crucial, encouraging fact: **the page components and the on-device data layer
already mostly exist.** Every module already has `js/modules/<m>/pages/*.ts`
(e.g. `vocabulary/pages/word_list_app.ts`, `language/pages/language_list.ts`,
`tags/pages/tag_list.ts`, `admin/pages/settings_form.ts`), built to mount on the
PHP views, and `js/shared/offline/local/repositories/` already serves languages,
texts, terms, words, review, settings, tags (read), activity, sentences against
IndexedDB. **Job A is mostly assembly, not new feature work.**

## The mechanical recipe (one bundle page)

Every Job-A page is the same four edits. Templates: `app/library.ts` (mount an
existing Alpine component) and `app/text.ts` (purpose-built API-client form).

1. **HTML shell** — `src/frontend/app/<page>.html`. The static markup the PHP
   view used to render, plus `<meta name="lukaisu-modules" content="...">` (so
   `main.ts` boots the right Alpine component) and any `<script
   type="application/json" id="...-config">` placeholder the component reads.
   For *mount-a-component* pages, don't hand-transcribe the view — **prerender
   it**: add the page to the `$PAGES` registry in `build/prerender-app-view.php`
   and run `php build/prerender-app-view.php <page>`. That resolves `__()` against
   the English locale and renders `IconHelper`/`PageLayoutHelper` to static markup
   (what the deleted `build/php-view-prerender.mjs` used to do), and wraps it in
   the standard shell. The output is committed; the app owns it thereafter.
2. **Boot entry** — `src/frontend/app/<page>.ts`:
   ```ts
   import { bootAppPage, initDataMode, injectConfig } from './boot';
   async function start() {
     await initDataMode();                       // local-first vs server, seed
     // ...resolve whatever the PHP used to inject, via the Api client...
     injectConfig('<id>', { /* config the component reads */ });
     await bootAppPage({ requireAuth: true });   // installs link router, imports @/main
   }
   void start();
   ```
3. **Vite entry** — add `<page>: resolve(__dirname, 'src/frontend/app/<page>.html')`
   to `rollupOptions.input` in `vite.app.config.ts`.
4. **Link routing** — add the server path → bundle page mapping in
   `app/router.ts` (`bundledPageFor()` + a `pageUrl.*` builder), so in-app links
   resolve locally instead of falling through to the remote server.

Then **delete** the PHP view(s) + route(s) the page replaces, and the partials
they `include`.

> **Data-layer gate per page:** the mounted component calls `/api/v1/*`; in
> offline mode those are intercepted by `local/router.ts`. A page only works
> offline if every endpoint it hits is handled there. The table below flags the
> pages that need new repository methods first.

## Job A — page-by-page plan

Ordered by value. Build top-down; ship + delete the PHP view as each lands.

| # | New bundle page | Replaces (PHP views) | Reuse component | Offline data status | Template |
|---|---|---|---|---|---|
| 1 ✅ | `words.html` (terms list) — **landed** | `Vocabulary/list_alpine`, `list_filter`, `show`, `*_result` | `vocabulary/pages/word_list_app.ts` | ✅ `terms/list`, `filter-options`, `bulk-action`, `inline-edit`, `for-edit` all in local router | mount |
| 2 ½ | `word.html` (term **edit**) — **landed**; **new deferred** | `Vocabulary/form_edit_existing/_new/_term`, `edit_*_result` | purpose-built form (like `text.ts`) | ✅ load+save+delete offline (added `GET /terms/{id}`); ⚠️ **new term not bundled** | form |
| 3 ✅ | `languages.html` (list) — **landed** | `Language/index` | `languageList` component (`language_list_component.ts`) | ✅ list/set-default/reparse/delete all in local router | mount |
| 4 ✅ | `language-edit.html` (settings) — landed; **wizard deferred** | `Language/form` (`wizard` → see note) | purpose-built form (like `word.ts`) | ✅ load+save offline (`GET`/`PUT /languages/{id}`) | form |
| 5 ✅ | `texts.html` (archived) — **landed**; *active manage* = `library.html` | `Text/archived_list` (active `edit_list` already in `library.html`) | `text/pages/archived_texts_grouped_app.ts` (+ `texts_grouped_app.ts` for `library.html`) | ✅ added `GET /languages/with-archived-texts` + single-text `POST /texts/{id}/archive`·`/unarchive` + `DELETE /texts/{id}` to the local router | mount + data |
| 6 ✅ | `text-edit.html` (full edit) — **landed** | `Text/edit_form` (full), `archived_form` | purpose-built form (like `word.ts`) | ✅ added local `GET`/`PUT /texts/{id}` (re-parse on body/lang change); importers stay server | form |
| 7 ✅ | `tags.html` (term + text tags) — **landed** | `Tags/tag_list`, `tag_form` | purpose-built form (legacy `tag_list.ts` is native-nav, not mountable) | ✅ added local `GET /tags/manage` + `PUT`/`DELETE /tags/{term,text}/{id}` (rename/delete; create-on-tagging keeps working) | form + data |
| 8 ✅ | `settings.html` (preferences) — **landed** | `User/preferences` (`Admin/settings_form` is server-only) | purpose-built form (like `language-edit.ts`; `settings_form.ts` is form-glue, not data-binding) | ✅ default language fully offline (`POST /settings` `currentlanguage`→`setCurrentLanguageId`); interface language server-only (offline ships English); the rest of `preferences.php` is server-consumed (deferred) | form |
| 9 ✅ | `text-check.html` (parse preview) — **landed** | `Text/check_form` | purpose-built page (legacy `text_check_display.ts` is a server-driven auto-init reader, not mountable) | ✅ added local `POST /texts/check` → `checkText` (on-device tokenizer); multi-word matching stays server-enhanced | form + data |
| 10 ✅ | `home.html` (dashboard) — **landed** | `Home/index`, `helpers` | `js/home/home_app.ts` (mounted; offline-safe sections only) | ✅ dashboard assembled on-device from `GET /languages` + `/texts/by-language` + `/texts/statistics`; Gutenberg/GDL/library-search are server-enhanced (omitted) | mount + data |
| 11 ✅ | `text-print.html` (plain print) — **landed** | `Text/print_alpine` (plain mode) | `text/pages/text_print_app.ts` (reused; +`getConfigRtl`) | ✅ added local `GET /texts/{id}/print-items` → `getPrintItems` (same word/occurrence data the reader uses); the annotated/edit "Improved Annotated Text" is server-only — no on-device store | mount |

**Reader/review partials already replaced** (delete when their parent route
goes, no port needed): all of `Review/Views/*` (13), and `Text/Views/`
`read_desktop`, `word_popover`, `word_modal`, `multi_word_modal`, `audio_player`.

### First PR (locks the pattern): `words.html` — ✅ done 2026-06-25

The terms list is the highest-value gap and has **zero data-layer work** — the
local router already serves every endpoint `word_list_app.ts` uses. As built:

1. **Prerendered** `Vocabulary/Views/list_alpine.php` → `src/frontend/app/words.html`
   via `build/prerender-app-view.php` (registry entry `words`), keeping its
   `<meta name="lukaisu-modules">` and `#word-list-config` script tag.
2. `src/frontend/app/words.ts`: `initDataMode()` → resolve `activeLanguageId`
   like `library.ts` does → `injectConfig('word-list-config', { activeLanguageId,
   perPage: 50 })` → `bootAppPage({ requireAuth: true })`. (`perPage` has no clean
   offline read-API; the component default + localStorage persistence cover it.)
3. `vite.app.config.ts`: added `words: resolve(__dirname, 'src/frontend/app/words.html')`.
4. `app/router.ts`: `pageUrl.words(query)` + mapped `/words` and `/words/edit`
   (both render the same SPA) in `bundledPageFor()`, carrying the query through.
5. Extended `cypress/app-e2e/offline-milestone.cy.ts`: boot → save a word → open
   the terms list → inline-edit a translation, asserting `apiAttempts === 0`.
   Verified: `npm run build:app && npm run typecheck` green, all 3 app-e2e specs pass.

**Deferred — do NOT delete the PHP route/view yet.** `routes.php` `/words` +
`/words/edit` and `Vocabulary/Views/list_alpine.php` (+ `list_filter`, `*_result`)
stay until **the cut-over**, because the PHP server still renders its *own* browser
PWA from these views (`ViteHelper.php` present) — deleting now would 404 self-hosters'
`/words` page and break export (POST `/words`), violating "keep PHP runnable during
the transition" (`BRIEFING.md`). The bundle page and the PHP page **coexist** through
Job A; the views are removed at the cut-over, when the PHP server's UI is itself
cut over to the bundle. (This corrects the original step 6, which deleted too early.)

### Page 2: `word.html` (term **edit**) — ✅ done 2026-06-25; **new term deferred**

Built as a **purpose-built API-client form** (like `text.ts`/`language.ts`), not a
prerender — the PHP `form_edit_*` views do native POSTs and render `*_result`
fragments, neither of which runs offline. Reached from the terms list's per-row
Edit link (`/words/{id}/edit` → `word.html?id=N`): loads the term, edits
status/translation/romanization/lemma/sentence/notes/tags, saves (`updateFull`) or
deletes — all on-device (offline E2E asserts `apiAttempts === 0`).

- **Data-layer gap closed:** there was **no offline `GET /terms/{id}`** (routeGet
  didn't handle it; the doc's earlier "✅" only covered *save*). Added
  `getTerm()` to `repositories/terms.ts` + the route in `local/router.ts`. It is a
  *superset* of the server's `GET /terms/{id}` (which omits `notes`/`tags`): offline
  returns them so the form prefills; in server-backed mode the server omits them and
  its `PUT` ignores them, so they degrade gracefully and are never clobbered.
- **"New term" (`/words/new`) is NOT bundled.** There is no clean offline/`/api/v1`
  contract for creating a *full* standalone term outside a text — `/terms/full`
  requires a text occurrence, and server-side `/words/new` is a native form, not
  JSON. Bundling it would need new API surface, and **PHP is frozen**. It stays
  server-only (falls through, like today). Revisit if/when a `POST /terms` that
  accepts full fields lands.
- **PHP deletion deferred** to the cut-over, same as page 1.

### Page 3: `languages.html` (languages list) — ✅ done 2026-06-25

The textbook **mount-a-component prerender** with **zero data-layer work** — the
local-first router already serves every endpoint `languageList` touches (`GET
/languages`, `GET /languages/definitions`, `POST /languages/{id}/set-default`,
`POST /languages/{id}/refresh`, `DELETE /languages/{id}`). As built:

1. **Prerendered** `Modules/Language/Views/index.php` → `src/frontend/app/languages.html`
   via `build/prerender-app-view.php` (registry entry `languages`). The view also
   calls `UrlUtilities::getBasePath()`, so the harness gained a `UrlUtilities`
   stub returning `''` (server-relative links; the client link router resolves
   them at click time — same identity treatment as `PageLayoutHelper::url`).
   Re-running the harness regenerates `words.html` byte-identically.
2. `src/frontend/app/languages.ts`: the simplest boot entry yet — `initDataMode()`
   → `bootAppPage({ requireAuth: true })`, **no `injectConfig`**. The component's
   `init()` loads everything (`LanguagesApi.list()` + `getDefinitions()`) itself,
   so there is no server-injected config to reproduce.
3. `vite.app.config.ts`: added the `languages` input.
4. `app/router.ts`: `pageUrl.languages()` + mapped the literal `/languages`.
   `/languages/{id}/edit` is **intentionally left to fall through** to the remote
   server — that single-language edit form is page 4, not bundled yet.
5. Extended the offline E2E: boot+seed → open the languages list → assert the
   seeded languages render → **set a non-current language as current**
   (`POST /languages/{id}/set-default` on-device), all at `apiAttempts === 0`.

**PHP deletion deferred** to the cut-over, same as pages 1–2.

### Page 4: `language-edit.html` (language settings) — ✅ done 2026-06-25; **wizard deferred**

Built as a **purpose-built API-client form** (like `word.ts`/`language.ts`), *not*
a prerender-and-mount. The table optimistically said "mount `language_form.ts`", but
that component is the **legacy** behavior: `form.php` is a native `<form method="post"
action="/languages/{id}/edit" op="Change">`, and `language_form.ts` only does
client-side validation before letting the native POST through — it never touches the
API. So mounting it offline would dead-submit, exactly the page-2 lesson. Reached from
the languages list's Edit links (`/languages/{id}/edit` → `language-edit.html?id=N`):
loads the language (`GET /languages/{id}` → `getLanguage`), edits its fields, saves
(`PUT /languages/{id}` → `updateLanguage`, which also re-parses the language's texts) —
all on-device (offline E2E asserts `apiAttempts === 0`).

- **Zero data-layer work** — the local router already served both `GET` and `PUT
  /languages/{id}`. The doc's "✅ create/update" held here (unlike page 2, where
  `GET /terms/{id}` was missing).
- **Scope = the round-tripping fields.** The form carries exactly `LanguageFull` /
  the update request (name, dict 1/2 URIs + popups, translator URI + popup,
  source/target codes, text size, character substitutions, the three parsing
  regexes, the four script checkboxes, export template, TTS JSON). Left out are the
  genuinely **server-enhanced** bits of `form.php`: the local-dictionaries table +
  import (Job B), the parser-type picker and local-dict lookup mode (no on-device
  contract — neither is in `LanguageFull`), and the live TTS check/test buttons
  (outbound network). Dictionary popups + the target code are *sent* (so they persist
  in server-backed mode) but the offline store drops them — they load blank and are
  never clobbered, the same graceful degradation as word.ts's notes/tags.
- **The standalone wizard (`wizard.php`) is NOT separately bundled.** Its job —
  picking L1/L2 to seed a *new* language's settings — is already served by the
  bundled "add a language" page (`language.html`), whose preset dropdown fills the
  same fields offline. `form.php`'s `?wizard=1` hand-off path (sessionStorage) is a
  server-form mechanism with no role in the bundle. Revisit only if a richer
  guided-setup flow is wanted on-device.
- **PHP deletion deferred** to the cut-over, same as pages 1–3.

### Page 5: `texts.html` (archived texts) — ✅ done 2026-06-25

The **mount-a-component prerender**, with a real **data-layer** addition (the page's
first such since page 2). The key finding while building it: the table's "manage +
archived" framing is **already half-done** — `library.html` mounts the *same*
`textsGroupedApp` component as `edit_list.php` (it *is* the prerendered active-manage
list, with the read/review/archive/delete/edit cards). So page 5's genuinely new
surface is the **archived** half (`archived_list.php` → `archivedTextsGroupedApp`),
plus the single-text data layer both halves share.

As built:

1. **Prerendered** `Modules/Text/Views/archived_list.php` → `src/frontend/app/texts.html`
   via `build/prerender-app-view.php` (registry entry `texts`, title "Archived
   Texts", modules `text`). The view needed three more harness shims beyond
   pages 1–4: an HTML-escaping `__e()`, a `PageLayoutHelper::renderMessage()`
   (no-op on the empty prerender message), and the real `FormHelper` +
   `SelectOptionsBuilder` (both pure, so `forTextSort()` / the archived-actions
   `<select>` render their exact server option lists — same "require, don't stub"
   treatment as `IconHelper`). Re-running the harness regenerates `words.html`
   and `languages.html` byte-identically (backward-compat gate).
2. `src/frontend/app/texts.ts`: resolves `activeLanguageId` exactly like
   `library.ts` (current language, else first) → `injectConfig('archived-texts-grouped-config', …)`
   → `bootAppPage({ requireAuth: true })`.
3. `vite.app.config.ts`: added the `texts` input.
4. `app/router.ts`: `pageUrl.archivedTexts()` + mapped `/text/archived`. The
   action-card cross-links already resolve — the archived page's "Active Texts"
   button (`/texts?…`) maps to `library.html`, the active list's "Archived Texts"
   button (`/text/archived?…`) maps here — matching the server's two-page UX.

**Data-layer gaps closed (the "+ data" half).** The archived page and the active
manage list both performed their per-text actions via **web routes** (raw
`fetch` / native form POST), never `/api/v1` — so they had no offline path. There
is also **no** single-text `/api/v1` archive/delete/unarchive on the server (only
`PUT /texts/bulk-action {action:'archive'|'delete', ids}`), so "match the server
contract" meant mirroring the **web-route shapes** as new *local-router-only* arms:

- `GET /languages/with-archived-texts` → `listLanguagesWithArchivedTexts()`
  (`repositories/languages.ts`) — `{languages:[{id,name,text_count}]}` for
  languages with ≥1 archived text. **This was the blocker for the list itself**:
  the grouped view loads it first, and it wasn't in the router.
- `POST /texts/{id}/archive` → `archiveText()` (flip `archivedAt`).
- `POST /texts/{id}/unarchive` → `unarchiveText()` (clear `archivedAt`).
- `DELETE /texts/{id}` → `deleteText()` (tombstone + drop occurrences/sentences/tags).
  All three are in `repositories/texts.ts`; the on-device store keeps active +
  archived rows in one table flagged by `archivedAt`, so archive/unarchive are
  reversible soft flips (the language repo's soft-delete is the reference).

`TextsApi` gained `archive`/`unarchive`/`deleteText` wrappers (via `apiPost`/
`apiDelete`). The two shared components route through them **only when
`isLocalFirst()`** — otherwise the original web-route `fetch`/form path runs
unchanged, so **the server PWA is byte-for-byte unaffected** (it has those web
routes; it has no `/api/v1` equivalents). A nice side effect: `library.html`'s
per-card archive/delete now also work offline. Offline E2E (`09-archived-texts`):
archive a seeded text from the library → it renders on `texts.html` from
IndexedDB → unarchive it there → it leaves the list, all at `apiAttempts === 0`.

- **Deferred:** the per-archived-text **edit** form (`/text/archived/{id}/edit`,
  `archived_form.php`) is **page 6** (`text-edit.html`), so it is left to fall
  through to the remote server for now. In the bundled app's **server-backed**
  mode the single-text archive/unarchive/delete arms aren't wired (no `/api/v1`
  counterpart; the JSON path there is `bulk-action`) — a pre-existing limitation,
  since those web-route actions already targeted the bundle origin, not the
  configured server. **PHP deletion deferred** to the cut-over, same as pages 1–4.

### Page 6: `text-edit.html` (text edit form) — ✅ done 2026-06-25

A **purpose-built API-client form** (like `word.ts` / `language-edit.ts`) — the
PHP `edit_form.php` / `archived_form.php` do native POSTs, so they can't run
offline. A **single page handles both** the active and archived cases: it's reached
from both lists' Edit links (`/texts/{id}/edit` from `library.html` and
`/text/archived/{id}/edit` from `texts.html`), loads the record, and uses its
`archived` flag to pick the post-save redirect (active list vs archived page). Edits
title / language / body / source / audio / tags; the offline E2E rewrites the body
and asserts the **reader re-renders the new tokens** at `apiAttempts === 0`.

- **Data-layer gap closed.** The doc's "✅ `texts` PUT" was optimistic — the local
  router had **no** `GET /texts/{id}` or `PUT /texts/{id}`, and the texts repo had no
  `getText`/`updateText` (the server exposes single-text edit only as a *web-route*
  form via the `UpdateText` use case; its `/api/v1` `texts` verbs are
  collection/`bulk-action`). Added `getText()` + `updateText()` to the texts repo and
  the two router arms. `updateText` **re-parses** (rebuilds sentences + occurrences,
  re-linking word statuses by `textLc`) when the body or language changed, so the
  reader reflects the edit; an unchanged body leaves the parsed structures untouched.
  `TextsApi` gained `get()` / `update()`.
- **Local-first only**, like page 5's per-text arms: there is **no remote
  `/api/v1/texts/{id}` GET/PUT** (PHP frozen), so in server-backed mode the lists'
  Edit links still reach the server's own form. Offline — the milestone path — is
  fully served.
- **Importers stay server.** The PHP edit form's file / URL / Gutenberg / GDL /
  transcription import panels are genuinely server-side (outbound bucket, Job B) and
  are **not** part of this offline editor; they remain on the server-rendered form.
- **PHP deletion deferred** to the cut-over, same as pages 1–5.

### Page 7: `tags.html` (tag management) — ✅ done 2026-06-25

A **purpose-built API-client page**, not a mount — the doc's "reuse `tag_list.ts`"
didn't survive contact: that legacy component drives **native navigation + native
POST bulk actions** (it never touches `/api/v1`), so mounting it offline would
dead-link. One bundled page shows **both** term tags and text tags (the server splits
them across `/tags/term` and `/tags/text`); each row renames or deletes inline.

- **Data-layer gap closed.** The server's `/api/v1` tags surface is **GET-only**
  (`/tags`, `/tags/term`, `/tags/text` — autocomplete + filter lists); tag *writes*
  are native web-route forms. So, like pages 5–6, the mutations are **local-router
  only**: added `GET /tags/manage` (every tag with id + usage count) and
  `PUT`/`DELETE /tags/term|text/{id}` (rename/delete, dropping the word/text
  mappings), backed by new `tags.ts` repo methods (`listTagsForManagement`,
  `rename*Tag`, `delete*Tag`) and a new `TagsApi` wrapper. In server-backed mode the
  tag pages still reach the server's own forms (no remote counterpart; PHP frozen).
- **Scope = rename + delete.** Creating a *standalone* tag is intentionally omitted —
  tags are created on demand when you tag a term (`setWordTags`) or a text
  (`setTextTags`), so an orphan tag has no use. This matches how the app already
  works and keeps the page to the genuinely useful management ops. Tag *comments* are
  likewise left out (rarely used; not in the offline contract).
- **Reachability note:** the bundled navbar (`GET /api/v1/navbar`) doesn't currently
  surface the tag pages, so `tags.html` is reached by direct path / `bundledPageFor`
  mapping (`/tags`, `/tags/term`, `/tags/text`) rather than a nav link — adding a nav
  entry is a separate, optional follow-up.
- **PHP deletion deferred** to the cut-over, same as pages 1–6.

### Page 8: `settings.html` (preferences) — ✅ done 2026-06-25

A **purpose-built API-client form** (like `language-edit.ts`/`word.ts`), not a
mount — the table's "reuse `settings_form.ts` (scoped)" didn't survive contact:
that component is form-dirty-tracking + theme-preview glue, it never reads or
writes settings. Reached from the navbar's "Preferences" link
(`/profile/preferences` → `settings.html`), which was a dead fall-through link
offline until now.

**Scope = the preferences the bundle actually honours.** The reconnaissance
finding that shaped the page: *most* of `preferences.php` has **no consumer in
the bundled client** — the per-page pagination counts, tooltip mode, review
timings, sentence counts, translation delimiters, etc. are read by the **PHP
renderer** at request time, and the offline reader config is hard-coded
(`local/text-assembly.ts:buildReadingConfig` never reads the settings store).
Porting them would ship dead controls. The whole `Admin/settings_form` is
SCOPE_ADMIN (feed limits, registration, update-check) — server/multi-user only.
So the honest page carries just two genuinely-working controls:

- **Default language** (`currentlanguage`) — **fully offline.** Saved through the
  API client (`SettingsApi.save` → offline `POST /settings`, which the local
  router routes to `setCurrentLanguageId`); the library and "add a text" pages
  read it back. This is the one preference that takes effect with no server, and
  the E2E target.
- **Interface language** (the UI locale) — **server-enhanced.** When connected,
  saving fetches the chosen catalog (`GET /api/v1/i18n/{locale}`) and reloads.
  Offline only English is bundled (`local/i18n.ts` resolves every locale to
  English), so the picker is **disabled with a note** — the same graceful
  degradation as language-edit's server-only fields.

**Zero new data layer** — the offline `POST /settings` arm (generic
`setSetting`, with `currentlanguage` special-cased) already existed; page 8 only
*consumes* it. (Reading a setting back has no offline router arm, but the page
needs none — it reads the current default via `GET /languages`'s
`currentLanguageId`, exactly like `library.ts`.)

1. New `src/frontend/app/settings.{html,ts}`; added the `settings` Vite input and
   the `/profile/preferences` → `settings.html` mapping (`bundledPageFor` +
   `pageUrl.settings`) so the navbar link resolves locally.
2. A **separate** offline E2E spec (`cypress/app-e2e/settings.cy.ts`): boot →
   open Preferences from the navbar → change the default language → it persists
   across a reload, all at `apiAttempts === 0`, with the interface-language
   picker asserted disabled offline.

- **PHP deletion deferred** to the cut-over, same as pages 1–7.

### Page 9: `text-check.html` (parse preview) — ✅ done 2026-06-25

A **purpose-built API-client page**, not a mount — the table's "reuse
`text_check_display.ts`" didn't survive contact: that component is a *server-
driven* auto-init reader that renders a JSON config the PHP server pre-computed,
and `check_form.php` itself does a native POST to `/text/check`. Neither runs
offline. So this is a small diagnostic page — pick a language, paste a text, hit
**Check** — that parses the text **on-device** and reports the same statistics
the server's "check a text" tool does: the reconstructed sentences, and the
distinct **word** / **non-word** tokens with their occurrence counts. Words you
already know (saved with a translation) are highlighted, mirroring the server's
"red = already saved". Nothing is persisted — it is read-only.

- **Data-layer gap closed.** Added `checkText()` to the texts repo (it tokenizes
  via the existing local parser — `parseText` + `languageToParserConfig` — and
  groups tokens exactly as the server's `checkValid` / `displayStatistics` do)
  and a `POST /texts/check` arm to the local router, with a `TextsApi.check`
  wrapper. This mirrors `TextParsingPersistence::checkValid`'s output shape
  (`[word, count, translation]` / `[nonword, count]`) so the on-device preview
  reads identically.
- **Local-first only**, like pages 5–8's per-resource arms: the server exposes
  `/text/check` only as a web-route form (no `/api/v1`, PHP frozen), so in
  server-backed mode the `/text/check` link still reaches the server's own form.
- **Multi-word matching stays server-enhanced.** Multi-word terms are never
  *created* on-device, so the offline preview reports no expressions
  (`multiWords` is always empty) rather than replicating the server's
  `tempexprs` expression-matching. The genuinely useful core — sentences + word
  / non-word lists + known-word marking — is fully faithful. Reached by direct
  path (the navbar surfaces no "Check" link), like `tags.html`.
- **PHP deletion deferred** to the cut-over, same as pages 1–8.

### Page 10: `home.html` (dashboard) — ✅ done 2026-06-25

A **mount** page that reuses the existing `homeApp` Alpine component
(`js/home/home_app.ts`), but with a **hand-built, offline-trimmed** shell rather
than a full prerender — `index.php` is dominated by **server-enhanced (Job B)**
content that can't run offline: the Gutenberg + GDL "suggested reads" rows and
the library-search/import modal all reach outbound discovery APIs. Prerendering
the whole view would ship those as dead controls, so the bundled dashboard keeps
only the offline-safe core: the welcome hero, the **continue-reading** card (with
the reader-coloured status bar), a **new-text** card, and a **browse-your-library**
card (the offline stand-in for the discovery search — it just links to the
bundled library).

- **Data assembled on-device, no new arms.** The PHP controller injected the
  dashboard config at request time; the boot entry rebuilds it from existing
  local endpoints — `GET /languages` (current language + name),
  `GET /texts/by-language/{id}` newest-first (the continue-reading text + total
  count), and `GET /texts/statistics` (that text's status breakdown, mapped to
  the component's `TextStats`). So page 10 needed **zero** local-router changes.
- **Inert warnings offline.** `homeApp` also drives the PHP-version / update /
  cookie warnings; the config passes `phpVersion: ''` (the version check no-ops)
  and `checkForUpdates: false` (no outbound GitHub call), so only the harmless
  client-side cookie check can ever fire.
- **Reachability = the home route.** Unlike the other optional pages, this one
  has a natural entry: `bundledPageFor` now maps `/` and `/index.php` →
  `home.html` (the server's home route), with `/texts` kept on `library.html`.
  So the navbar logo lands on the dashboard while the Texts nav opens the
  library — matching the server's two surfaces. (First-run/connect still drops
  the user straight in the library, unchanged.)
- **PHP deletion deferred** to the cut-over, same as pages 1–9.

### Page 11: `text-print.html` (plain print) — ✅ done 2026-06-25

A **mount** page that reuses the existing `textPrintApp` Alpine component
(`text/pages/text_print_app.ts`), but **plain-print only**. The component renders
three modes (plain / annotated / edit); the annotated *"Improved Annotated Text"*
persists a hand-edited annotation blob (`texts.annotated_text`) the bundle has no
on-device store for, so the page pins the mode to `plain` and serves only that.
Reached from the reader's and library's printer links (`/text/{id}/print-plain`).

- **Data-layer gap closed (for plain print).** Added `getPrintItems()` in a new
  `local/repositories/print.ts` and a `GET /texts/{id}/print-items` arm to the
  local router. It mirrors `TextPrintService::getTextItemsForApi` +
  `preparePlainPrintData` (see `TextAnnotationApiHandler::getPrintItems`) — the
  same stored occurrences + words the reader assembles, plus word tags joined
  like `getWordTagList`. So plain print — status-filtered word annotations
  (translation / romanization / tags, behind / in front / ruby) — is **fully
  on-device** (the E2E asserts `apiAttempts === 0`).
- **Purpose-built shell, not a prerender.** `print_alpine.php` branches on
  `$mode` and needs controller-computed service HTML (`navLinksHtml`,
  `annotationLinkHtml`, `editFormHtml`); a hand-authored `text-print.html` mounts
  the component with a real `#navbar-root` and the plain-mode DOM (the
  status-range `<select>` options are lifted verbatim from
  `SelectOptionsBuilder::forWordStatus`, so the filter matches the server).
  Added one CSP-safe accessor (`getConfigRtl`) to the component for the `:dir`
  binding — backward-compatible, unused by the server view.
- **Print filters persist offline.** The component saves `currentprint*` via
  `POST /settings` (generic `setSetting`); `getPrintItems` reads them back with
  the same defaults as `TextPrintService` (ann 3 / status 14 / placement 0).
- **Annotated/edit modes stay server-backed.** `bundledPageFor` maps only
  `/text/{id}/print-plain`; the annotated `/text/{id}/print` (and `/print/edit`)
  fall through to the remote server's web UI when connected — graceful
  degradation, same as the other server-only features.
- **PHP deletion deferred** to the cut-over, same as pages 1–9.

## The cut-over (the payoff) — ✅ in-repo cut-over landed 2026-06-25

The PHP server now serves the **bundle** as its own browser UI for every Job-A
surface, talking to its own `/api/v1` in same-origin server-backed mode. What
landed (three commits: enablement → endpoints → redirect):

1. **Serve `dist-app/` under `/app/`** (`BundleController`, the `/app` prefix
   route behind `AuthMiddleware`, and `Router::resolveStaticAsset`'s `/app/* →
   dist-app/*` mapping). The shell is served by PHP so it can inject a per-session
   `<meta name="csrf-token">`, the base path, and a `{sameOriginServer}`
   runtime-config blob; `boot.ts` reads that and runs server-backed against this
   origin (cookie auth) instead of going local-first. `client.ts` makes
   `credentials:'same-origin'` explicit and routes a mid-session 401 to `/login`.
   The Dockerfile + `build:all` now build and ship `dist-app/`. The packaged
   F-Droid/offline path is unchanged (no injected config → false).
2. **Redirect the Job-A page routes** into the bundle: every reading/learning GET
   route 302s to its `/app/*.html` page (`BundleController::redirect`, mirroring
   `app/router.ts` `bundledPageFor()`), registered last so it overrides the
   page-render handlers for GET only. POST/JSON/DELETE data routes keep their
   controllers; annotated print, Job B/C, the API and bundle assets are untouched.
3. **New `/api/v1` endpoints** so the offline-first pages also work *server*-backed
   (they had local-router-only data arms with no server counterpart):
   `GET`/`PUT /texts/{id}` (single-text edit), `GET /tags/manage` +
   `PUT`/`DELETE /tags/{term,text}/{id}` (tag management), `POST /texts/check`
   (parse preview → `TextParsing::checkTextDetailed`). Guarded by
   `BundleCutoverTest`.

### Scope corrections discovered during the cut-over

- **`ViteHelper.php` + the `vite.config.ts` web build STAY.** The original step 2
  ("delete them") is **not yet possible**: every surviving PHP page — Job B
  (feeds/books/dictionaries) and Job C (login/admin/profile) — renders through
  `PageLayoutHelper::renderPageStart*` → `ViteHelper` → `dist/`. Deleting it would
  break login and admin. Removal is gated on Jobs B + C, not Job A.
- **The cross-repo `git mv src/frontend` into `lukaisu` (step 3) is deferred.**
  The "one consumer" premise is currently circular: the server still serves
  `dist-app/`, so it still consumes the frontend. Moving the source out would
  require the server to pull the built bundle *back* from `lukaisu` — new infra to
  design with the `lukaisu` agent. Coordinate before doing it.
- **Deleting the now-dormant Job-A views/controllers is deferred to a follow-up**
  (see *Open items*). The GET page routes redirect, so the views are unreachable,
  but the deletion is a large, entangled refactor (controllers shared with kept
  POST/data routes + tests) best done **after a live smoke-test** confirms the
  bundle's server-backed mode and exactly which web routes it still exercises.

After this cut-over the PHP server's user-facing rendering is the bundle for all
Job-A surfaces; it still renders Job B + Job C pages (and keeps `ViteHelper`/
`dist/` for them) until those jobs land.

### ✅ Live verification — done (2026-06-27)

Same-origin serving is now **live-verified** against `docker compose` (MariaDB +
Apache/PHP 8.4), in **both** `MULTI_USER_ENABLED=false` and `true`, on top of the
prior unit/routing coverage. Confirmed end-to-end:

- Every Job-A GET route (`/`, `/texts`, `/text/1/read`, `/words`, `/languages`,
  `/tags`) **302s to its `/app/*.html` page**, and the bundle is served with the
  injected `csrf-token` + `sameOriginServer":true` config.
- The server-backed `/api/v1` arms return **200 and writes persist** (DB-verified):
  `GET`/`PUT /texts/{id}`, `GET /texts/by-language/{id}`, `GET /tags/manage`,
  `PUT /tags/term/{id}`, `POST /texts/check`.
- **CSRF is enforced** on same-origin writes (a PUT without the token → 403),
  authenticated or not.
- **Multi-user gating**: unauthenticated web routes → `/login`, API → 401; after
  login the same flows work, and first login claims the orphan (`user_id 0`) rows.

## Job B — server-enhanced surfaces (defer)

Feeds (`Feed/Views/*` — 12), books/EPUB (`Book/Views/*` — 4), dictionary import
(`Dictionary/Views/*` — 2). These are the **outbound bucket** — a phone can't
fetch arbitrary URLs. Port to client pages **only as discovery becomes
server-optional**, and hide them when no server is connected. Not on the
no-server path; safe to leave server-rendered until then.

## Job C — admin / auth / profile (dies with PHP)

`Admin/Views/*` (8: settings, users, backup, server-data, wizard, install-demo,
dashboard) and `User/Views/*` (auth/profile/statistics/OAuth — ~12, minus
`client_auth` which is already the client `/connect`). **These are not client
rendering** — they administer the PHP server and gate multi-user data. For the
local-first single-user app they simply don't exist. They are deleted when the
PHP server is decommissioned (gated on Python sync/auth — `sync-contract.md`,
`auth.md`), not ported. `User/preferences` and `User/statistics` are the
exceptions → folded into Job A (`settings.html`, and a local stats view).

## Full deletion checklist (all 93, grouped by disposition)

Legend: **[A]** port to bundle page · **[del]** partial, delete with parent ·
**[B]** server-enhanced (defer) · **[C]** decommission with PHP.
Progress markers: **✅** deleted · **◐** partially deleted · **⏸** deferred.

**Status (2026-06-27).** Six Job-A surfaces retired so far — Home, Review,
User/preferences, Tags (+ `AbstractCrudController`), Language, and the dead
multi-word + hover-create Vocabulary renders. The remaining **⏸** items are
either blocked on an in-browser E2E (the reader/review popup fallbacks — the
`/word/edit`, `/word/edit-term`, reader popups) or are intentionally kept
server-rendered (the text importers, annotated display/print, and the tag
create/edit forms).

- **Home (2): ✅ deleted** — index `[A→home]`, helpers `[del]` (HomeController retired, `e63b172`)
- **Language (3): ✅ deleted** — index `[A→languages]`, form `[A→language-edit]`, wizard `[A→language-edit]` (whole web `LanguageController` + both views gone, `a2a08d6`)
- **Text (14): ⏸ deferred** — edit_list `[A→texts]`, edit_form `[A→text-edit]`, archived_list `[A→texts]`, archived_form `[A→text-edit]`, check_form `[A→text-check]`, print_alpine `[A→text-print]`, display_header/main/text `[A→text-print]`, read_desktop/word_popover/word_modal/multi_word_modal/audio_player `[del]` (reader). *edit_list/archived_list/check_form already removed earlier. The rest is **not** safely deletable yet: edit_form/archived_form keep the server-side importers (Job B), display_*/print are intentionally server-rendered, and the reader popups are E2E-gated (see Vocabulary).*
- **Vocabulary (20): ◐ partial** — list_alpine/list_filter/show `[A→words]`, form_new/form_edit_new/form_edit_existing/form_edit_term `[A→word]`, **form_edit_multi_new/form_edit_multi_existing `✅ deleted` (`d3a967b`)**, bulk_translate_form/upload_form/starter_vocab `[A→word]` (server-enhanced bits stay — keep), `*_result`: **edit_multi_update + hover_save `✅ deleted` (`d3a967b`)**; save/bulk_save/edit/edit_term/all_wellknown/upload `[del]` ⏸ deferred (rendered by `/word/edit`, `/word/edit-term`, `/word/set-all-status`, `/word/upload`, `/word/bulk-translate` — live reader/review fallbacks, **E2E-gated**)
- **Tags (2): ✅ done** — tag_list `[A→tags]` deleted (render retired, `3448f06`; `AbstractCrudController` deleted with it); **tag_form *stays server-rendered*** — the bundle `tags.html` is rename+delete only, so the create/edit forms have no client counterpart (PHP frozen)
- **Review (13): ✅ deleted** — review_desktop `[A→review]` + all 12 others `[del]` (render controller + views gone, `b8fda4f`)
- **Settings/Prefs: ✅ deleted** — `User/preferences` `[A→settings]` (preferences form render retired, `8a1f283`)
- **Feed (12):** all `[B]`
- **Book (4):** all `[B]`
- **Dictionary (2):** import, index `[B]`
- **Admin (8):** all `[C]`
- **User (13):** client_auth `[done = /connect]`, preferences `[A→settings]`, statistics `[A→stats]`, login/register/forgot/reset/recover/recovery_code/profile/profile_single_user/google_link_confirm/microsoft_link_confirm `[C]`

## Definition of done

- **Job A done. ✅** `app/` ships every management page; `bundledPageFor()` has no
  reading/learning path falling through; the offline E2E exercises list → edit
  across terms/texts/languages/tags at `apiAttempts === 0`.
- **In-repo cut-over done. ✅** The PHP server serves the bundle (under `/app/`) as
  the default UI for all Job-A surfaces; the page routes redirect into it; the
  bundle works server-backed via `/api/v1` (the 3 new endpoints close the last
  gaps). **Caveats vs the original plan:** `vite.config.ts` web build +
  `ViteHelper.php` **remain** (Job B/C PHP pages need them) and `src/frontend/`
  has **not** moved to `lukaisu` (deferred — see the cut-over section). The
  now-dormant Job-A views/controllers are **not yet deleted** (follow-up below).
  *Same-origin serving is live-verified (2026-06-27) — see the note below.*
- **Views deleted (follow-up): ◐ in progress (2026-06-27).** Six surfaces
  retired so far — Home, Review, User/preferences, Tags (+ `AbstractCrudController`),
  Language (whole web controller + views), and the dead Vocabulary multi-word +
  hover-create renders. Each was a focused commit verified at psalm 0 / phpcs 0 /
  PHPUnit green. `ViteHelper`/`dist/` and all POST/JSON/DELETE data routes the
  bundle uses are kept. **Remaining** (see the checklist's ⏸ items): the rest of
  Vocabulary's `*_result`/`form_*` and all of Text — entangled with live
  reader/review fallbacks and gated on the in-browser E2E below, or intentionally
  kept server-rendered (importers, annotated display/print, tag forms).
- **All-views done (post-PHP-decommission):** Jobs B and C resolved; `ViteHelper`
  + the `vite.config.ts` web build removed; `src/frontend/` relocated to
  `lukaisu`; `Views/` directories removed. *Blocked on Python sync/auth.*

## Open items / risks

- **Live verification of same-origin mode — done ✅ (2026-06-27).** Verified live
  against `docker compose` in both `MULTI_USER_ENABLED` modes: redirects, bundle
  serving + config injection, `/api/v1` 200 + write persistence, CSRF enforcement,
  and multi-user gating (see the verification note above). This **unblocks**
  deleting the dormant Job-A PHP views.
- **Delete the dormant Job-A views/controllers — ◐ in progress (2026-06-27).**
  The cleanly-verifiable surfaces are done (Home, Review, User/preferences, Tags,
  Language, and the dead multi-word + hover-create Vocabulary renders — see the
  checklist). **What's left is blocked on an in-browser E2E:** the Vocabulary
  `*_result`/`form_*` views and the Text reader popups are rendered by routes that
  are still live as bundle/reader/review *fallbacks* (`/word/edit`,
  `/word/edit-term`, `/word/inline-edit`, `DELETE /words/{id}`, the reader frame
  popups). Static analysis can't prove these dead; `npm run e2e` against a live
  server is needed to confirm the fallbacks never fire before removing them.
  edit_form/archived_form (importers), display_*/print, and the tag create/edit
  forms are **not** deletion targets — they stay server-rendered by design.
- **Data-layer gaps — all closed, both layers.** The offline (local-router) arms
  landed per page; the cut-over added the matching **server** `/api/v1` arms:
  `GET`/`PUT /texts/{id}`, `GET /tags/manage` + `PUT`/`DELETE /tags/{term,text}/{id}`,
  `POST /texts/check`. (`print-items` already existed server-side.)
- **Don't fork the frontend.** `src/frontend/` stays here for now (the server
  still builds + serves `dist-app/`); coordinate the eventual `git mv` into
  `lukaisu` — and the reverse-pull infra it needs — with the `lukaisu` agent.
