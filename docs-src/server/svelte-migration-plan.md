# Alpine → Svelte 5 Migration Plan

The ordered work-list for converting the bundled app's rendering from Alpine.js
to Svelte 5. Each **division** below is one self-contained, independently
shippable migration unit — the granularity a migration loop iterates through
(one division per pass). Background + rationale: [`local-first.md`](./local-first.md)
→ *Rendering: Alpine → Svelte*.

> **Baseline:** this work builds on branch `feat/svelte-word-list`, which carries
> the Svelte toolchain (svelte + `@sveltejs/vite-plugin-svelte`, `svelte.config.js`,
> the `*.svelte` eslint block, `svelte-check` wired into `npm run typecheck`) and
> the reference port (`WordList.svelte`). **Merge it to `main` first**, or branch
> the loop from it — `main` alone does not yet have the toolchain.

## The rule (every division)

- **Coexist, don't stop the world.** Svelte islands mount alongside Alpine;
  Alpine owns only `x-data` nodes. Port one screen at a time.
- **Keep the Alpine component file.** It still backs the **server PWA** (built
  from the same `src/frontend/` source via `vite.config.ts`). It no-ops in the
  app once its `x-data` node is gone. Delete it only at the **PWA cut-over**, not
  per-division. (Same rule the docs apply to PHP views.)
- **Full parity.** Match every feature of the Alpine version — filters, persisted
  UI state, bulk actions, i18n via `t()`, RTL/`ttsClass`, lucide icons
  (re-run `initIcons()` from a `$effect`). CSP forbids `innerHTML`: render text,
  use the `stripMarkdown()` pattern (see `WordList.svelte`) instead of `{@html}`.
- **Same data layer.** Call the existing `*Api` / Dexie modules; don't fork them.

## Per-division recipe

1. **Port** the Alpine component(s) → a `.svelte` component (runes: `$state` /
   `$derived` / `$effect`, `SvelteSet` for selections), full parity.
2. **Cut over the page:** replace the prerendered `x-data` region in
   `src/frontend/app/<page>.html` with a `<div id="…-root">` mount point; rewrite
   `src/frontend/app/<page>.ts` to `mount(Component, { target, props })` after
   `bootI18n()`, then `bootAppPage(...)` for the shared shell.
3. **Verify (all must pass):**
   - `npm run typecheck` (tsc + `svelte-check`, 0/0)
   - `npm run lint`
   - `npm run build:app`
   - CSP grep: `grep -rE "\beval\(|new Function\(" dist-app/assets/*.js` → none
   - `npm test`
4. **Commit** per division (conventional commit, e.g. `feat(app): migrate the
   review surface from Alpine to Svelte 5`).

## Divisions (ordered)

Order = docs priority (highest-pain reading/learning first) → content screens →
ancillary → shared chrome → final Alpine removal. Shared chrome (Tier 4) is the
**hard prerequisite** for removing Alpine entirely.

### Tier 0 — reference (done)

- [x] **Terms list** — `words.html` · `wordListApp`/`wordListFilterApp`/`wordListTableApp` → `WordList.svelte`

### Tier 1 — reading/learning core (highest pain)

- [x] **1. Review surface** — `review.html` · `reviewApp`, `tableReview` → `ReviewPage.svelte` + `review_store.svelte.ts` (Alpine `review_view.ts`/`review_store.ts` kept for PWA)
- [x] **2 + 3. Reader + word interactions** — `read.html` · `textReader`, `wordPopover`, `wordModal`, `wordEditForm`, `multiWordModal` → `TextReaderApp.svelte` + `WordPopover`/`WordModal`/`WordEditForm`/`MultiWordModal` + `word_store`/`word_form_store`/`multi_word_form_store.svelte.ts`. Ported together (inseparable — shared Alpine stores). `text_renderer.ts` reused as-is; `audioPlayer` left as Alpine (→ #4). Alpine originals kept for PWA.
- [x] **4. Audio player** — `audioPlayer` (`media/audio_player_alpine.ts`) → `AudioPlayer.svelte`, hosted inside `TextReaderApp` (only `read.html` used it; review uses feedback sounds, not the player). Alpine original kept for PWA.

### Tier 2 — library & content

- [x] **5. Library (active texts)** — `library.html` · `textsGroupedApp`, `dropdownToggle` → `TextList.svelte` (dropdown folded in). Alpine original kept for PWA. (Scout: #5/#6 are NOT a shared component — distinct data model/UI/actions.)
- [x] **6. Archived texts** — `texts.html` · `archivedTextsGroupedApp` → `ArchivedTexts.svelte` (grouped-by-language, collapse-state localStorage, per-language lazy-load/pagination). Alpine original kept for PWA.
- [x] **7. Home / dashboard** — `home.html` · `homeApp`, `discoverBooks`, `gutenbergSuggestions`, `gdlSuggestions` → `HomePage.svelte` (+ `DiscoverBooks`/`GutenbergSuggestions`/`GdlSuggestions` + `lib/suggestions.ts`). Alpine originals kept for PWA. (Streak/`calendarHeatmap` are NOT on home — they live in the activity module; `librarySearch` is a separate modal, left as Alpine.)

### Tier 3 — feeds, languages, auth, print

- [x] **8. Feeds** — `feeds.html` · feed-manager SPA (`feedList`/`feedFilter`/`articleList`/`articleFilter`/`feedForm`/`feedNotifications`) → `FeedsPage.svelte` + 6 child components + `feed_manager_store.svelte.ts`. Server gate preserved (no mount in local-first). Alpine originals kept for PWA. NOTE: `feedBrowse`/`feedLoader`/`feedWizard*` are legacy PHP-only pages (NOT in `feeds.html`) — out of the app's scope, not ported.
- [x] **9. Languages list** — `languages.html` · `languageList` → `LanguageList.svelte` (set-current in place, custom delete modal + canDelete guard). Alpine original kept for PWA. (Language form/wizard are a separate Tier-5 division.)
- [x] **10. Connect / auth** — `index.html` · `clientAuth` → `ConnectPage.svelte` (login/register/recovery inline; token storage + connect-URL probe + redirect plumbing reused as-is). Alpine original kept for PWA. (`registerForm`/`resetPasswordForm` are server-page-only — not in `index.html`, not ported.)
- [x] **11. Text print** — `text-print.html` · `textPrintApp` → `TextPrintApp.svelte` (option→render bitmask + placement formatters verbatim; @media print CSS kept global). Alpine original kept for PWA.

### Tier 4 — shared chrome (cross-cutting; on every page)

`main.ts` imports these and runs `Alpine.start()` + `mountNavbar()` on every page,
so they render even on the plain-DOM pages. **Alpine cannot be removed until these
are Svelte.** Migrate carefully (a regression hits every screen).

- [x] **12. Navbar + streak + theme toggle** — `navbar`/`navbarStreak`/`themeToggle` → `NavBar.svelte` (+ `NavbarStreak`/`ThemeToggle` children), mounted globally from `main.ts` (replaces `mountNavbar()`; `Alpine.start()` kept for PWA server pages). Shared change: the PWA build now compiles Svelte (`vite.config.ts` got the plugin). Alpine navbar files kept (tree-shaken). NOTE: `streakDisplay` is an activity-page component (not in the navbar) — separate, not done here.
- [x] **13. Footer / theme / offline / select** — no app porting work remained:
  `themeToggle` shipped with #12 (nested in the navbar); `footer` and
  `offlineIndicator` are registered but **never mounted** in any `app/*.html`
  (no `#footer-root` / `#offline-indicator-root` — they don't render in the app,
  so there's nothing to migrate for parity); `offlineButton` is not used on any
  app page or island; `searchableSelect` is used only by server-rendered PHP
  forms (`archived_form.php`, `edit_form.php`), **deferred to the PWA cut-over**.

> **Milestone — the bundled app is now Alpine-free.** No `app/*.html` has any
> `x-data` attribute (only cut-over comments), and the last JS-injected Alpine
> chrome (the navbar) is now a Svelte island. `Alpine.start()` still runs from the
> shared `main.ts` but finds nothing to bind on app pages (no-op). Alpine is still
> *bundled* (imported by `main.ts`) and still drives the **PWA's** server-rendered
> pages + `searchableSelect` forms — so it cannot be removed from the shared entry
> yet (see #14).

### Tier 5 — optional consistency (no Alpine to remove)

These app pages are **already plain-DOM purpose-built API forms** — no Alpine.
Converting them to Svelte is consistency polish, not Alpine removal; do only if
wanted: `word`, `language`, `text`, `language-edit`, `text-edit`, `tags`,
`dictionaries`, `settings`, `text-check`.

### Final — remove Alpine

- [ ] **14. Retire Alpine — BLOCKED on the PWA cut-over (needs a decision).** The
  app side is ready (Tiers 1–4 done; no `x-data` in any app page). But Alpine
  cannot be dropped from `main.ts` / the vite configs yet because **`main.ts` is
  shared** and the **PWA's server-rendered PHP views still emit Alpine `x-data`**
  (read/library/feeds/admin views) plus the `searchableSelect` server forms. So
  full removal (`@alpinejs/csp` + the alias, the CSP-build workarounds, jQuery
  `jq_pgm.ts`, the kept Alpine `*_app.ts`/store files) requires first converting
  (or retiring) the PWA's PHP-rendered pages to Svelte — a separate, larger
  initiative beyond this app-focused plan. Two paths to decide between:
  (a) **convert the PWA pages too** (port the PHP views' Alpine to Svelte islands,
  then drop Alpine everywhere); or (b) **build-split** — keep Alpine only in the
  PWA (`vite.config.ts`) bundle and strip it from the app (`vite.app.config.ts`)
  via a conditional/lazy `main.ts` Alpine load. Both need explicit direction.

## Notes for the loop

- **Pick the next unchecked division each pass**, port it, run the verify gate,
  commit, check it off here.
- **Split when too big.** The reader (#2/#3) and feeds (#8) are larger than the
  word list; a pass may take a sub-screen and leave the rest unchecked.
- **Don't fork the data layer or the frontend.** Build in
  `lukaisu-server/src/frontend/`; coordinate the eventual relocation to `lukaisu`.
- **Per-component count for sizing:** feed 11, vocabulary 10, admin 7 (PWA-mostly),
  text 6, auth 3 — see `grep -rn "Alpine.data(" src/frontend/js`.

## Phase 2 — retire Alpine (convert the PWA / server-rendered pages too)

User-authorized 2026-06-30. The bundled **app** is Alpine-free; this phase removes
Alpine from the **PWA** (the PHP server's own web UI). Key finding from the
architecture scout: the PWA cut-over mechanism is **NOT** "rewrite each PHP view in
Svelte" — it's **redirect the PHP GET route to the static `dist-app/*.html` shell**
that already mounts the Svelte island (the documented "Job-A" recipe in
`php-view-retirement.md`). Recipe per screen: build the island as `app/<page>.html`
+ `<page>.ts`, add the `vite.app.config.ts` entry, add the mapping to all **three
mirror maps** (`routes.php` redirect block · `BundleController::mapPathToBundlePage`
· `router.ts::bundledPageFor`), then delete the PHP view + its overridden route.

Gate each division: `typecheck` · `lint` · `build:app` · `build` (PWA) · `vitest` ·
`./vendor/bin/psalm` (+ `composer test:no-coverage` when a test DB is available).
**Live routing (e2e/Cypress) needs a browser+server — can't run headless here;
flag PWA routing changes for the user's manual/on-device QA.**

Divisions (tasks #22–#32):
- **D1 — delete dead Job-A views + dead Alpine renderers** (route-overridden
  read_desktop/word_*/audio_player/edit_form + the `*_app.ts` kept "for the PWA"
  that are now dead). Pure cleanup, no ports.
- **D2 — converge Category-A** (island already exists): add redirects for
  `/connect`→ConnectPage and `/feeds[/manage]`→FeedsPage; delete those PHP views.
- **D3 — port the PWA-only clusters** (new islands): a) vocabulary imports,
  b) tags forms, c) dictionary import, d) **feed wizard/browse (~3100 LOC, heaviest)**,
  e) user auth views + statistics, **f) admin cluster — DECISION: port vs defer
  (it dies with the PHP decommission)**, **g) annotated print — DECISION: port vs
  keep server-only**.
- **D4 — shared chrome** (searchableSelect/footer/offline + `BaseController::message`
  inline notification + `PageLayoutHelper`); only after no reachable PHP page renders
  the chrome. High risk (every shell).
- **D5 — final Alpine deletion**: strip Alpine from `main.ts`, the `@alpinejs/csp`
  alias from **both** vite configs, the `alpine` manualChunk + `[x-cloak]` safelist,
  `@alpinejs/csp`/`@types/alpinejs` from `package.json`. Highest blast radius.

Notes: **jQuery is already gone** (`hover_intent.ts` is vanilla TS, not jQuery — it
stays). The `script-src 'self'` CSP stays (Svelte is CSP-clean too).

### Phase 2 — progress & remaining work (approach: **C now → A eventually**)

**Decision (2026-07-01):** proceed with **C (prioritized)** now and **eventually reach
A (full retirement — no `x-data` anywhere, `@alpinejs` dropped)**. So nothing is
permanently deleted for being a "deprecation candidate" (A wants it ported); C only
**orders** the work: high-value user-facing screens first, the heavy/low-value ones
(feed-wizard, admin) and the POST-result tails last, then D5.

**Done (committed on `main`, gate-verified incl. full PHP suite; pushed through
`757f3b7`, then Phase-2 commits `b0c5b1e`→`cc408c1` local):**
- [x] **D1** — deleted 38 dead Job-A files (views + coexistence-kept Alpine renderers).
- [x] **D2** — converged `/connect`→ConnectPage, `/feeds[/manage]`→FeedsPage.
- [x] **D3a** — vocab cluster: `starter-vocab` + `bulk-translate` (Alpine fully gone);
  `word-upload` **partial** (GET page → Svelte island; POST→result flow still
  server-renders Alpine — see the tail note below).

**The validated recipe (proven 3×) — per PWA-only screen:**
1. `app/<page>.html` (thin shell + offline notice if server-gated + `<div id="<page>-root">`)
   and `app/<page>.ts` (`initDataMode` → server-gate → `bootI18n` → fetch config → `mount`
   → `bootAppPage`). Copy `app/starter-vocab.{html,ts}` as the template.
2. Add the `vite.app.config.ts` input.
3. Add the route to all 3 mirror maps (`routes.php` GET→`$bundleRedirect`,
   `BundleController::mapPathToBundlePage`, `router.ts::bundledPageFor` + a `pageUrl.*`
   helper). Id-carrying paths use a regex → `<page>.html?<param>={id}` (see starter-vocab).
4. Verify-then-delete the PHP view + Alpine `.ts` + test + barrel + the overridden GET
   route — **but KEEP anything a live non-GET (POST) route still renders** (flag it).

**Two findings that shape every remaining port:**
- **(config API)** PWA screens inject their config as a PHP `<script type="application/json">`
  blob with no API. Port = island **+ a thin `GET …/config` endpoint** on the existing
  controller returning `JsonResponse::success([...])` (template: `StarterVocabController::config()`).
- **(POST→Alpine-result tail)** Some screens POST to a server-rendered **result page** that
  itself uses Alpine (`word-upload`'s manual result; likely admin/feed-wizard). The GET-form
  port does NOT remove that Alpine. **Fully retiring it (needed for A/D5) means rebuilding the
  POST flow client-side** — the POST returns JSON (or an API endpoint is added) and the island
  renders the result. Track these as their own sub-tasks; they gate D5.

**Remaining work, in C order:**
1. **D3e — user auth views + statistics** (do FIRST; highest-value, user-facing).
   `login/register/forgot_password/reset_password/recover_password.php` (`registerForm`
   ~155, `resetPasswordForm` ~122 + mostly-static forms hydrated by `form_validation`/
   `form_initialization`) and `statistics.php` (`statisticsApp`/`streakDisplay`/
   `calendar_heatmap`, Chart.js ~644). Watch for auth POST→re-render tails (login/register
   error re-render). Note: ConnectPage already covers `/connect`; these are the distinct
   `/login`,`/register`,`/password/*`,`/profile/statistics` routes.
2. **D3b — tags forms** (`tag_form.php`: term + text tag new/edit). Small; needs a select
   widget — build a minimal Svelte searchable-select or use a native `<select>` (the full
   `searchableSelect` port is D4).
3. **D3c — dictionary import** (`import.php`; `dictionary_import` ~71, `local_dictionary_panel`
   ~277, `curated_dictionaries` ~599). **Reuse the committed `CuratedDictBrowser.svelte`**
   (built during D3a).
4. **D3a-tail — word-upload POST result** — rebuild the manual-upload result client-side so
   `upload_result.php`/`upload_form.php` + `word_upload.ts`/`wordUpload*App` can be deleted.
5. **D3d — feed wizard/browse cluster** (~3100 LOC, heaviest): `new/edit/multi_load/
   edit_text_form/browse/wizard_step1-4.php` + `feed_wizard_store`. Split into sub-screens.
   Do after FeedsPage is the sole feeds SPA (done in D2). NOTE `Feed/Views/index.php` is
   rendered by `/feeds/edit` (kept in D2) — it dies with this cluster.
6. **D3f — admin cluster** (~1360 LOC): `backup/install_demo/server_data/settings_form/
   users/wizard.php` (backupManager/userManagement/settingsFormApp/serverDataApp/
   tableManagementApp/ttsSettingsApp/wizardModal). Deferred under C; **ported (not deleted)
   under A**.
7. **D3g — annotated print**: `print_alpine.php` annotated mode (`/text/{id}/print` +
   `/print/edit`) + `text_print_app.ts`'s annotated branch (plain-print already → TextPrintApp).
   Deferred under C; ported under A.
8. **D4 — shared chrome**: `searchable_select`, `footer`, `offline-button/indicator`,
   `BaseController::message()`'s inline `x-data` notification, `PageLayoutHelper` chrome →
   Svelte (NavBar/ThemeToggle/NavbarStreak already done). Only after no reachable PHP page
   renders the chrome. High risk (every shell).
9. **D5 — final `@alpinejs` deletion** (= reaching A): strip Alpine from `main.ts` (import,
   `window.Alpine`, `$t`/`$markdown` magics, `Alpine.start()`; keep the navbar mount + i18n);
   remove the `'alpinejs'→'@alpinejs/csp'` alias from **both** vite configs; drop the `alpine`
   manualChunk + `[x-cloak]` safelist; remove `@alpinejs/csp` + `@types/alpinejs` from
   `package.json`; update the `SecurityHeaders.php` comment. Gated on **zero** reachable
   `x-data` remaining (grep `src` for `x-data` + `Alpine.data(` = empty). Highest blast radius.

**Verification reality:** the static gate (typecheck/svelte-check · lint · build:app · build
(PWA) · vitest · psalm · `composer test:no-coverage`) is strong but **cannot exercise live
routing** (Cypress `e2e`/`e2e:app` need a browser+server). Every redirect + server-gated island
needs a **manual pass on a running server** before deploy. Outstanding QA from D1–D3a:
`/connect` first-visit login (multi-user `AuthMiddleware` bounces unauth → `/login`),
`/feeds[/manage]`, and the three vocab screens (server flow + offline "connect a server" notice).

### Progress update 2026-07-01 (batch 2 — stats + auth + tags + dictionary)

Landed on `main` (gate-verified, incl. full psalm/phpunit):
- [x] **D3e-stats** (`61130f4`) — `/profile/statistics` → server-gated Svelte island;
  `StatisticsController::config()` JSON endpoint; deleted the 3 Alpine components.
- [x] **D3e-auth** — the whole auth surface rebuilt on the **token API** (the chosen
  "full bundle-redirect" path):
  - `D3e-auth-1` login (`ea24bb6`): `GET /login` → `LoginPage` island → `POST /api/v1/auth/login`
    (sets **both** session cookie + bearer, so the remaining PHP pages keep auth). Added an exact
    guest `GET /app/login.html` serve route to punch through the `/app` auth prefix.
  - `D3e-auth-2` register + password (`a894ae0`): `RegisterPage` (+ ALTCHA + inline recovery-code)
    / `Forgot`/`Reset`/`RecoverPasswordPage`; **session-on-register fix** (register now creates a
    session like login); **3 new endpoints** `POST /api/v1/auth/password/{forgot,reset,recover}`
    (public, `[RateLimit, Csrf]`, not CSRF-exempt — `apiPost` sends the token); retired
    `recovery_code.php` + its route. Guest `/app/*.html` serve routes for each shell.
- [x] **D3b tags** (`bef4380`) — `tag-form.html` island (term/text · new/edit); **completed the
  tags API** (added `POST /tags/{term,text}` create + single-tag GET) instead of a config endpoint.
- [x] **D3c dictionary** (`ea72792`) — `dictionary-import.html` server-gated island **reusing
  `CuratedDictBrowser.svelte`**; drives list/delete/curated-import via `/api/v1/local-dictionaries`;
  the native multipart file upload keeps its `POST /dictionaries/import` (a bearer client can't
  ship a server temp file). Integration fixups in `33eceec`.

**Coexistence still on disk (retired as a cluster at D5):** `login.php` + `UserController@login/loginForm`
+ `POST /login`; `register/forgot_password/reset_password/recover_password` controller POST handlers +
their now-redirect-only `*Form` GET methods; `tag_form.php` + Term/TextTagController POST/DELETE (POST
re-renders it on error); dictionary `processImport` POST + `Views/index.php`. All unreachable by GET;
kept because backend tests still assert them.

**Process lesson — parallel worktree agents (honor "don't do items one by one"):** ran D3b/D3c/D3e-auth-2
as 3 concurrent `isolation:'worktree'` agents. **Gotcha:** the worktrees branch from `origin/main`
(`757f3b7`), NOT local `main` HEAD, so each agent lacked the unpushed Phase-2 work (templates
`starter-vocab`/`login`, `CuratedDictBrowser`) and re-created/vendored some of it. Integration was
`git cherry-pick <branch-tip>` per division onto `main`, resolving the shared-wiring conflicts (the 4
mirror files + `BundleCutoverTest` + `Endpoints.php`) by **union**, plus de-duping the re-created login
infra (`uiLocaleConfig`, `/app/*.html` serve routes) and dropping a stale `client_auth` import main had
already deleted. Agents self-gate typecheck+lint only; the **central full gate (psalm/phpunit) is where
integration bugs surface** — it caught a `list<string>` coercion + a stale tags 405→404 test.
**Next time: push `main` first** so worktrees branch from current work (or integrate the same way).

**Remaining (C order):** word-upload POST-result tail · **D3d feed wizard/browse (heaviest)** · D3f
admin (defer→A) · D3g annotated print (defer→A) · D4 shared chrome · D5 final `@alpinejs` deletion.
Note for D5: `grep x-data src/frontend/app/*.html` still hits the **earlier-tier** shells
(read/words/home/texts/library/index/languages/text-print) — verify those are inert (islands own the
DOM) or scrub them before dropping Alpine.

### Progress update 2026-07-01 (batch 3 — word-upload tail + feed new/edit form)

Landed on `main` (full central gate green: typecheck 0/0 · eslint · psalm 0 · phpcs 0-new ·
build:app · build · vitest 3703 · **phpunit 8915, 0 failures**):
- [x] **D3a-tail** (`b9622ca`) — word-upload POST result rebuilt client-side. `POST /word/upload`
  now returns JSON `{lastUpdate, rtl, recno}` (or `{error}`) from BOTH `handleUploadImport`
  (op=Import) and `handleDictionaryImport` (op=ImportDictionary) instead of `include`-ing a view;
  `WordUpload.svelte`'s manual tab `fetch()`-posts (multipart FormData) and renders a new
  `ResultDisplay.svelte` (paginates the imported terms via the existing `GET /api/v1/terms/imported`).
  **Fully retired**: `word_upload.ts` (all Alpine — `wordUploadPageApp`/`wordUploadFormApp`/
  `wordUploadResultApp`/`curatedDictBrowser`, all verified unreachable), `upload_form.php`,
  `upload_result.php`, the dead `displayUploadForm()` private method, and the Alpine
  `word_upload.test.ts`. Net −1,838 LOC. `POST /word/upload` route + `GET /word/upload/config` KEPT.
- [x] **D3d-form** (`4c3051f`) — `/feeds/new` + `/feeds/{id}/edit` GET forms → one `FeedFormPage.svelte`
  island (create/edit modes) on the existing `/api/v1/feeds` CRUD. Added authed JSON config routes
  `GET /feeds/new/config` + `GET /feeds/{id}/edit/config` (`FeedController@configNew/configEdit`,
  edit folds the `getFeed` prefill in); ported the options-string (de)serialization into
  `feed_form_options.ts` with round-trip tests (canonical order, no trailing comma — matches the
  server's `getNfOption` parse + `rtrim` on save). Edit prefill uses `?feed={id}`. Deleted `new.php`.
- **Integration fix** (`be2fee0`) — the word-upload agent's self-gate **stalled before phpunit**, so
  the central gate caught 2 stale reflection assertions in `TermImportControllerTest` (required
  `displayUploadForm`; `upload()` returns `void`) — updated to the new JSON shape. (Lesson restated:
  agents self-gate typecheck+lint; **only the central psalm/phpunit run exercises the backend** —
  always run it, especially when an agent dies mid-gate.)

**D3d is NOT complete — deliberate scope call.** The feed cluster (~3,100 LOC) is only *partly* a
mechanical port. Ported: the new/edit **form** (existing CRUD API). NOT ported (task #27, `D3d-rest`):
the **wizard** steps 1-4 (a PHP-`$_SESSION` 4-step flow needing **4-5 net-new `/api/v1/feeds/wizard/*`
endpoints** — remote feed parse, article preview, XPath validate/apply, save), **feed-load progress**
(`/feeds/{id}/load` streaming), **multi-load** (couples into load-progress: posts `load_feed=1` to
`/feeds`), and the **browse/index** GET-orphans. That's net-new backend *design*, not a port — it wants
its own focused pass (a Plan agent first), not a blind background worktree agent. Two coexistence files
therefore stay on disk (verify-then-delete guard did its job): **`edit.php`** (still rendered by
`showEditForm()` via the live legacy `/feeds/edit?edit_feed=1` route + 4 tests) and
**`feed_form_component.ts`** (the wizard's `wizard_step1.php` manual-setup tab still binds
`x-data="feedForm"`). So the Alpine `feedForm` component is NOT gone yet — it dies with the wizard.

**~~⚠ Pre-existing security finding~~ — FALSE POSITIVE, corrected 2026-07-01 (see D3d-rest batch 4).**
The earlier claim here was that `getFeed`→`getFeedById`→`find()` had **no user-scoping** (feed IDOR in
multi-user mode). That was a mis-trace: I read `AbstractRepository::query()` = `QueryBuilder::table()` and
stopped, but **`QueryBuilder` auto-applies user scope inside its execution methods** (`firstPrepared`/
`getPrepared`/`updatePrepared`/`deletePrepared`) via its own `USER_SCOPED_TABLES` registry, which includes
`'news_feeds' => 'user_id'` (`QueryBuilder.php:84`). So `find()` was always emitting `… AND user_id = ?`
in multi-user mode; the read/update/delete/deleteArticles paths were all already gated, and the
`deleteArticles` "scoped" comment was already true. Present since the initial commit — no live vuln.
(Phase E of D3d-rest still hardened it defensively — see batch 4.)

**Remaining (C order):** **D3d-rest** (feed wizard + load + multi-load + browse — net-new API) · D3f
admin (defer→A) · D3g annotated print (defer→A) · D4 shared chrome · D5 final `@alpinejs` deletion.

---

## D3d-rest plan (drafted 2026-07-01, from a read-only Plan pass over the real feed code)

**Reframing — this is ~70% deletion, ~30% net-new, NOT the heavy build the LOC count implied.**
The already-shipped `/feeds` + `/feeds/new` + `/feeds/{id}/edit` bundle redirects and the
`FeedsPage`/`FeedFormPage` islands already retired browse, feed-list, article-import, feed CRUD, and
single-feed load — all through existing `/api/v1/feeds*`. What remains is mostly **already-dead orphan
code** plus **one genuinely net-new thing**: the 4-step visual XPath wizard.

**Pivotal open decision — KEEP or KILL the visual XPath wizard.** Verified: the wizard
(`/feeds/wizard`, steps 2-4) is **reachable-but-unlinked — no live entry point.** Every link into it
comes from dead views (`edit.php:42`) or the wizard's own steps; `router.ts:293` is only a comment. The
live `FeedsPage.svelte:94` "Feed Wizard" button points at **`/feeds/new`** → the *plain form* island, not
the wizard. So **killing the wizard breaks nothing user-facing** (the branded button already opens the
form). This is a product call, not a code call, and it forks the whole division:
- **KILL** → D3d-rest collapses to Phase A + the IDOR fix (pure deletion); **D5 unblocks fast**. No new API.
- **KEEP** → also build Phase C (2 endpoints) + Phase D (the Svelte wizard island, ~2-3d, the only heavy part).

### Already-dead — delete regardless of the wizard decision (Phase A, independent, low-risk)
`browse.php`, `index.php`, `edit_text_form.php`, `edit_text_footer.php`, `edit.php`, `wizard_step1.php`
(its manual tab is the *only* remaining `x-data="feedForm"` consumer — grep-verify, then
`feed_form_component.ts` + the Alpine `feed_form_api.ts` die too; **the shipped `FeedFormPage.svelte` does
NOT depend on them** — that earlier coexistence note is now stale), plus Alpine
`feed_browse/index/text_edit_component.ts`. Strip the marked-items branches from `FeedIndexController`
(`index:62`, `processMarkedItems:95-246`) — the `feeds/articles/import` API replaces them. Update
`BundleCutoverTest` (drop the `POST /feeds/new|edit` preserved rows if native POSTs are retired for the API).

### Load-progress + multi-load (Phase B, do after A — both touch Feed{Index,Load}Controller)
Single-feed `/feeds/{id}/load` progress is superseded by `feeds_api.loadFeed` (inline in the manager).
Multi-load: fold into `FeedList.svelte` as a bulk action (checkbox loop over the existing
`POST /api/v1/feeds/{id}/load`, which already returns per-feed `{message,imported,duplicates}`), OR just
delete `multi_load.php` (it's unlinked). Then delete `renderFeedLoadInterfaceModern`,
`feed_loader_component.ts`, `feed_multi_load_component.ts`, and the `/feeds/{id}/load` + `/feeds/multi-load` routes.

### Net-new wizard API — only if KEEP (Phase C, backend-only, parallelizable with A/B)
XPath preview is **already client-side** (`xpath_utils.ts` `document.evaluate`); the server's only job is
to fetch+clean article HTML. So just **two thin stateless endpoints** wrapping code that mostly exists:
- **`POST /api/v1/feeds/wizard/detect`** `{sourceUri}` → `{detectedFeed, feedText, items[], articleSources[]}`.
  Reuse `FeedLoadApiHandler::detectFeed()` (`:212`, already exists, just **unrouted**). 422 `{error}` on
  bad/unreachable/SSRF-blocked URL (`safeHttpGet` already guards private ranges).
- **`POST /api/v1/feeds/wizard/preview-article`** `{link,title,inlineText?,articleSource,charset?,redirect}`
  → `{html, sourceUri}`. Extract `previewArticle()` from `FeedWizardController::getStep2FeedHtml()`
  (`:625`) → `extractTextFromArticle(...,'new','iframe!?!script!?!...')` returns the whole cleaned doc.
- **Save** = existing `POST /api/v1/feeds` / `PUT /api/v1/feeds/{id}` with
  `article_section_tags = articleSelectors.join(' | ')`, `filter_tags = filterSelectors.join(' | ')`,
  `options = buildOptionsString()` (the store already builds these). **No net-new save endpoint.**
- Register in `Endpoints.php` (`feeds/wizard/detect|preview-article => ['POST']`) + branch in
  `FeedApiHandler::routePost` (`:457`).

### Wizard Svelte island — only if KEEP (Phase D, depends on C, the heavy part ~2-3d, one worktree)
Port `feed_wizard_store.ts` → a `.svelte.ts` runes store (session state maps ~1:1; the ~200 LOC of
`FeedWizardController` session-shuffling evaporates — step N's output is in-memory for step N+1). One
multi-step island `FeedWizardPage.svelte` (client-side step router) + 4 step components; reuse
`xpath_utils.ts`/`highlight_service.ts` (framework-agnostic) unchanged; extract a shared
`FeedOptionsFields.svelte` from `FeedFormPage.svelte` for step 4. Add `app/feed-wizard.{html,ts}`
(server-gated like `feeds.ts`), vite input, 3-mirror-map wiring, flip `/feeds/wizard` to redirect, and
repoint `FeedsPage`'s "Feed Wizard" button to `/feeds/wizard`.

### Security — Phase E (~~fix the feed IDOR~~ → the IDOR was a FALSE POSITIVE; hardened defensively anyway)
The plan drafted this as a real vuln fix. On execution it turned out there was **no live IDOR**:
`find()` runs through `QueryBuilder`, which **auto-applies** `AND user_id = ?` for `news_feeds` (its
`USER_SCOPED_TABLES` registry, `QueryBuilder.php:84`) inside `firstPrepared`/`getPrepared`/`updatePrepared`/
`deletePrepared` — I'd stopped my trace at `query()` and missed it. Proven empirically (generated SQL per
mode) + confirmed by git blame (present since the initial commit). So read/update/delete/deleteArticles were
already gated and the `deleteArticles` comment was already accurate. **Phase E still shipped** as
defense-in-depth: an explicit multi-user-gated `where('user_id', …)` in `MySqlFeedRepository::find()` so the
invariant lives at the repository boundary (no longer silently depends on the shared registry), a corrected
comment, and 6 DB-guarded multi-user regression tests (`FeedMultiUserIsolationTest`) that lock it. Note the
plan's instinct to also scope `findNeedingAutoUpdate` was checked and **declined** — every caller is an HTTP
request path (no cron/CLI), so its existing per-user auto-scope is correct as-is.

### Sequencing / parallelizability
Phase 0 (keep-or-kill decision, blocking) → **A, C, E are independent** (safe concurrent worktrees) → B
after A → D after C (serial, one worktree, coupled shared store). If KILL: only A + B + E. Critical path
if KEEP: 0 → C → D.

### Top risk (if KEEP): browser-vs-PHP XPath parity
In-browser `document.evaluate` generates the saved selectors; PHP `DOMXPath` (`ArticleExtractor`) re-runs
them at load. Divergence = right element in preview, nothing extracted at load. **Pre-existing**, but the
port must keep `preview-article` returning byte-identical cleaned HTML to what the loader sees, and preserve
the `§` parent-separator + `contains(concat())` idioms in `xpath_utils.ts`. **Verification without a live
browser+server:** PHPUnit contract tests for both endpoints against in-memory XML/HTML fixtures (fetch/parse
are split, so no network); Vitest for the store serialization round-trip + `xpath_utils` in jsdom; extend
`BundleCutoverTest::newApiEndpointsProvider`. Real remote-feed fetch + preview↔load XPath parity need
**manual server QA** on 2-3 real feeds — cannot be automated here.

### Progress update 2026-07-01 (batch 4 — D3d-rest EXECUTED, decision = KILL) ✅ DONE

User chose **KILL** the visual XPath wizard (verified: no live entry point). D3d-rest ran as 2 parallel
worktree agents; full central gate green (typecheck 0/0 · eslint · psalm 0 · phpcs 0-new · build:app ·
build · vitest 3347 · **phpunit 8705, 0 failures**):
- [x] **Phase A+B — kill dead cluster** (`5f76a8d`): deleted **45 files** (10 views: `wizard_step1-4`,
  `browse`, `index`, `edit`, `edit_text_form`/`_footer`, `multi_load`; 4 PHP: `FeedWizardController`,
  `FeedIndexController`, `FeedLoadController`, `FeedWizardSessionManager`; 14 Alpine incl. all 4 wizard
  steps, browse/index/text-edit/loader/multi-load/**feedForm** components, `feed_wizard_store`,
  `xpath_utils`, `highlight_service`, `feed_wizard_types`; 17 tests). Modified 11 (routes: dropped
  `/feeds/wizard`, `/feeds/{id}/load`, `/feeds/multi-load`, legacy `/feeds/edit`; slimmed
  `FeedController`/`FeedEditController`/`FeedFacade`/`FeedServiceProvider`/`feed/index.ts`; fixed `psalm.xml`
  — its `Feed/Views` suppression paths broke config parse once the dir was gone). **Feed Alpine is now 100%
  retired.** No live referrer forced any keep.
- [x] **Phase E — feed IDOR** (`e13804e`): FALSE POSITIVE (see corrected note above); shipped as
  defense-in-depth + `FeedMultiUserIsolationTest` (6 DB-guarded tests).
- Post-integration: fixed a stale doc-comment in `feed_form_options.ts` (referenced the deleted
  `feed_form_component.ts`).

**D5 readiness scan (2026-07-01, post-feed-kill).** Remaining reachable Alpine (`Alpine.data(` ×22 regs /
11 PHP `x-data` views): **D3f admin** (backup/settings_form/server_data/users/install_demo/wizard +
`table_management`/`user_management`/`tts_settings`/`server_data`/`backup_manager`/`settings_form`), **D3g
annotated print** (`print_alpine.php`, `text_print_app`), **D4 shared chrome**
(navbar/theme_toggle/searchable_select/footer/navbar_streak/offline-*), plus `text_list`,
`text_suggestions`, `language_wizard_modal`, `local_dictionary_panel`, and the **coexistence views** kept
from earlier tiers (`tag_form.php`, `login.php`, `edit_form.php`). So **D5 is still blocked** on D3f + D3g +
D4 + retiring those coexistence views — the feed cluster is done but it was not the last Alpine.

**Remaining (C order):** D3f admin (defer→A / decision) · D3g annotated print (defer→A / decision) · D4
shared chrome · coexistence-view cleanup · D5 final `@alpinejs` deletion.
