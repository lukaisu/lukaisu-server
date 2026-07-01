# Frontend Relocation (Piece 2) — sequencing plan

> **Goal:** make the `lukaisu` app **own** the shared frontend, so the app builds
> standalone (no `cd ../lukaisu-server`) and the server becomes **headless**
> (`/api/v1` + optional Python edge, no browser UI). This is *Piece 2* from
> `BRIEFING.md` → *Rendering hollow-out*.
>
> **Paired docs:** `BRIEFING.md` (Piece 1/2 framing), `php-view-retirement.md`
> (per-view Job A/B/C triage), `svelte-migration-plan.md` (the Alpine→Svelte
> work that unblocked this), `../../../lukaisu/FDROID.md` (why the app wants it).
>
> **Status:** DRAFT (2026-07-01). Not started. **Governing decision (§0) is
> made: Option A — the headless server drops its browser UI entirely.** Not
> release-critical (the own-repo F-Droid build works today with both repos
> present); execute when the server-UI retirement is worth doing.

---

## 0. The one decision that governs everything

The shared frontend (`src/frontend/`) has **two consumers**:

- `vite.app.config.ts` → `dist-app/` — the **app** bundle (Alpine-free since D5).
- `vite.config.ts` → `dist/` — this **server's own browser UI** (PHP views that
  boot `main.ts` via the `lukaisu-modules` meta from `PageLayoutHelper.php`).

A clean move requires the frontend to have **exactly one consumer**. So the whole
plan hinges on: **what happens to the server's browser UI — specifically the
admin cluster — in a headless world?**

| Option | Server keeps a browser UI? | Frontend move | When it fits |
|---|---|---|---|
| **A. Drop it** (recommended) | No — manage via `/api/v1` / CLI / future Python | Clean wholesale move | You don't ship the server's web UI (current stance) |
| **B. Isolate admin** | A *tiny standalone* PHP+Alpine admin that **vendors its own Alpine**, not the shared frontend | Clean move; admin stays behind | You may want a browser admin later but won't couple it |
| **C. Keep full legacy PHP UI** | Yes | **No move** — use a shared npm package (`@lukaisu/frontend`) instead | You must keep serving the legacy full-stack UI to self-hosters |

Options A and B both free the frontend to move and yield a headless (or near-
headless) server. **C is the "don't do Piece 2" path** — it keeps a server UI at
the cost of a publish/versioning pipeline, and is only justified if existing
self-hosters must keep the PHP browser UI.

> **DECIDED (2026-07-01): Option A.** The headless server drops its browser UI
> entirely — no `dist/`, no Vite, no Alpine, no PHP views. Admin/settings/backup
> are managed via `/api/v1`, a CLI, or the future Python edge. This means Phase R
> **drops** every remaining server-rendered view (including the admin cluster)
> rather than porting or isolating it, and Phase C strips the frontend toolchain
> wholesale. No net-new admin UI is built in this repo.

Everything below assumes **A**.

---

## 1. Sequencing verdict: retire-then-move

Do **not** move the frontend first. Retiring the server-side consumers first
means you move a **single-consumer** tree (mechanical) instead of splitting a
shared one mid-flight. Concretely:

```
Phase R — sever every server-side consumer of src/frontend/   (in lukaisu-server)
Phase M — git mv the frontend into the app + rewire builds     (both repos)
Phase C — strip the frontend toolchain from the server         (lukaisu-server)
```

Phase R is the only substantive work; M and C are mechanical once R lands.

---

## 2. Phase R — sever the server's frontend dependency

**Precise scope = every PHP surface that boots `main.ts` or mounts a shared
Svelte island.** The chokepoint is `PageLayoutHelper.php` (`lukaisu-modules`
meta). Find the surface with:

```bash
grep -rln "lukaisu-modules" src --include=*.php          # standard-layout boots
grep -rln "x-data\|Alpine.data\|x-init" src --include=*.php   # server-rendered Alpine
```

The remaining server-rendered views, by retirement job:

| Cluster | Views | Fate under A/B |
|---|---|---|
| **Coexistence forms** (Job A tail) | `Tags/Views/tag_form.php`, `User/Views/login.php`, `Text/Views/edit_form.php` (searchableSelect) | Already have client equivalents (tags / connect+login / text-edit). **Cut over to bundle-serving under `/app/`** (like the Job-A cut-over) or drop. Retires their `main.ts` boot. |
| **Server-enhanced** (Job B) | feeds, dictionary import, book/EPUB import | Port to client (feeds form already ported this session). Hidden offline. |
| **Annotated print** (Job B tail) | `Text/Views/print_alpine.php` | Niche. Drop from headless server, or isolate (Option B). |
| **Admin / auth** (Job C) | `Admin/Views/{server_data,backup,users/list,settings_form,wizard,install_demo}.php` | **The real remnant.** Option A: drop (API/CLI/Python later). Option B: isolate as standalone admin vendoring its own Alpine. Either way, **stop importing the shared frontend.** |

**R exit criterion (the gate for Phase M):**

```bash
grep -rln "lukaisu-modules" src --include=*.php   # → empty (nothing boots main.ts)
```

Once no PHP view boots `main.ts`, the server no longer needs `main.ts`, the
Alpine module barrels, or `@alpinejs/csp`. Delete the server entry
(`src/frontend/js/main.ts`) and drop the `alpinejs → @alpinejs/csp` alias from
`vite.config.ts`. (The app already runs off `client.ts`; `main.ts` is server-only
after D5.)

> **Note on the Job-A `/app/` cut-over:** confirmed 2026-07-01 — the cut-over
> block (`routes.php` 796–848) 302s reading/learning GETs to `/app/*`, which
> `BundleController` serves from `dist-app/` booting `boot.ts` → `client.ts`
> (Alpine-free). So Job-A imposes **no `main.ts` dependency**; only the clusters
> below still boot `main.ts`. Under Option A the `/app/` serving itself is also
> dropped (server serves zero HTML) — see division R6.

### 2.1 Phase R execution breakdown (divisions, like the D-series)

**Current server-HTML surface (verified 2026-07-01 against `routes.php`).** What
still renders server HTML (boots `main.ts` via `PageLayoutHelper`), by cluster:

- **Admin (Job C):** `/admin`, `/admin/{backup,wizard,install-demo,settings,server-data}`, `/admin/users/*` → `AdminController`, `UserManagementController`.
- **Profile (Job C):** `GET /profile` → `UserController@profileForm`. (`/profile/preferences` + `/profile/statistics` already redirect to bundle.)
- **Books (Job B):** `/books`, `/book/{id}`, `/book/import` → `BookController`.
- **Dictionary list (Job B):** `/dictionaries`, `/languages/{id}/dictionaries` → `DictionaryController@index`. (import GET already bundled.)
- **Annotated print (Job B tail):** `/text/{id}/print`, `/print/edit` → `TextPrintController` (`print_alpine.php`). (print-plain already bundled.)
- **Dead-but-undeleted Job-A render branches:** `TextController@{read,new,editSingle,edit,archived,archivedEdit,check,display}`, `TermDisplayController@listEditAlpine`, `TermEditController@{createWord,editWord,…}` — overridden for GET, views still on disk.

> **HAZARD — app-facing data routes must survive the HTML purge.** A set of
> non-`/api/v1` routes are **server-enhanced data**, not browser UI, and the
> server-connected app calls them: `*/config` JSON boots (`/word/upload/config`,
> `/word/bulk-translate/config`, `/languages/{id}/starter-vocab/config`,
> `/feeds/new/config`, `/feeds/{id}/edit/config`, `/profile/statistics/config`)
> and native-POST data handlers (`/word/upload`, `/word/bulk-translate`,
> `/languages/{id}/starter-vocab/{import,enrich}`, `/feeds/*` POST,
> `/dictionaries/import` POST, `/tags/*` POST). Deleting these with their views
> breaks the app's connected mode. Under Option A they must be **moved under
> `/api/v1`** (or explicitly confirmed unused first). R0 settles this.

**Divisions** (each gated: `psalm --threads=1` · `phpcs PSR12` · `composer
test:no-coverage` · `typecheck` · `build:app`; commit per division):

| Div | Scope | Kind | Risk |
|---|---|---|---|
| **R0** | **Audit (no deletion).** Grep `src/frontend/js` for every non-`/api/v1` path it `fetch()`es → definitive keep/kill list; classify each route browser-UI vs app-facing. | read-only | — |
| **R1** | Delete the dead (GET-overridden) Job-A render branches + view files, keeping POST/DELETE/data branches. Render halves of `TextController`/`TermDisplayController`/`TermEditController`; `read`/`edit_form`/`new`/`archived`/`check`/`display`/`words`/`tag_form` views. | mechanical del | low (dead for GET) |
| **R2** | Annotated print: `TextPrintController@{printAnnotated,editAnnotation,deleteAnnotation}` + `print_alpine.php` + routes 121–137. (Reverses D3g under Option A.) | mechanical del | low |
| **R3** | Books + Dictionary-list: `BookController` + Book views; `DictionaryController@{index,preview}` HTML + Dictionary index view. Preserve dict-import POST per R0. | del + preserve | med |
| **R4** | Admin + Profile (Job C): `AdminController`, `UserManagementController`, admin views, `profileForm` view + routes. Keep `StatisticsController@config` per R0. | del | med |
| **R5** | **Contract decouple.** Move R0-flagged app-facing routes under `/api/v1` (starter-vocab, bulk-translate, upload, feeds config, dict import, stats config). API work, not deletion. | design + build | high |
| **R6** | Drop bundle-serving + Alpine: delete the cut-over redirect block + `BundleController` + `/app` prefix; delete `main.ts` + Alpine module barrels + `@alpinejs/csp` + the `alpinejs` alias; delete `vite.config.ts` PWA build (`dist/`, `sw.ts`, `ViteHelper`, `PageLayoutHelper`). **Gate:** `lukaisu-modules` grep empty; `@alpinejs` gone. | mechanical del | med |
| **R7** | Auth/session teardown: retire session-form POST handlers (`login`/`register`/`password`) if R0 confirms unused; decide cookie-session fate vs OAuth. **Auth-sensitive — may defer.** | del | high |

R1–R4 are mechanical deletion (safe, do now). R5 is genuine API work (the
"contract decouple" the decoupling audit flagged). R6 is the payoff — Alpine
dies and `main.ts` goes. R7 is auth-sensitive and can defer. **After R6, Phase M
(the frontend move) is unblocked.**

> **RE-SCOPE (2026-07-01, discovered mid-R1).** "Delete dead render" is only
> clean for **purely-presentational** pages. Several old server controllers
> interleave the dead GET render with **POST business logic that may be the sole
> implementation of a feature** — e.g. `TextCrudController@{new,editSingle,edit}`
> carry subtitle parsing, auto-split-to-book, and bulk mark-actions. These are
> NOT safe to delete blind; they need `/api/v1` parity proof first. So the
> deletion of *CRUD-with-logic* controllers **merges into R5** (verify API
> parity → then delete), while R1 keeps only the **pure dead renders**
> (`TextReadController@{read,check}`, list/form renders with no unique logic,
> `tag_form`, the Alpine words-list render). Net: R1 shrinks and de-risks; R5
> grows to "verify-parity-then-delete, per module."

> **R1 OUTCOME + a second audit gap (2026-07-01).** R1 landed as **one** clean
> deletion — the reader + parse-preview render paths (`470b2c5`). The Alpine
> **words-list** render looked next, but deleting it broke `BundleCutoverTest`:
> `registerWithMiddleware('/words', …)` bound **all methods**, so it also served
> **`POST /words` = terms export** — a *native form* submit (`WordList.svelte`
> sets `form.action='/words'`) that R0's `fetch()`-only audit **missed**.
> **Lesson: the app-facing keep-list must include native form POSTs, not just
> `fetch()`.** Reverted. Consequence: essentially **every remaining server route
> is either app-facing (native POST/fetch) or POST-entangled** → they all move to
> **R5** (build the `/api/v1` endpoint + repoint the frontend off `basePath()`,
> then delete). So R1 is **done**; R2 = drop the server-only annotated-print
> browser feature (live, not app-facing); R3/R4 fold into R5's per-module
> parity-then-delete. **R5 edits `src/frontend/js` (bundled into the app), so it
> is app-affecting — coordinate before starting.**

> **CLEAN-DELETION PHASE COMPLETE (2026-07-01).** R1 + R2 (`470b2c5`, `765e60c`)
> were the *only* server-only surfaces. Probing further confirmed **R3 (books)
> and R4 (admin/profile) are BOTH app-entangled**, not server-only:
> - **Books:** the app's reader chapter-nav (`book_nav_renderer.ts` → `/book/{id}`)
>   and EPUB import nav (`text_suggestions.ts` → `/book/import`) link to server
>   book pages.
> - **Admin/profile:** the app's Svelte navbar (`navbar_renderer.ts`) renders
>   `/admin/{backup,settings,users,server-data}` + `/profile` links,
>   `WordUpload.svelte` links `/admin/backup`, and **`app/settings.ts` imports
>   `@modules/admin/api/settings_api`** — so the admin module ships in the app
>   bundle.
>
> So **everything past R2 is R5** (repoint/rethink the app reference + build the
> `/api/v1` endpoint, then delete) — there is nothing left to delete without
> touching `src/frontend/js`. **Deletion track stops here; R5 is the coordination
> boundary.** R3/R4 fold into R5's per-module parity-then-delete.

> **R5 RECIPE (per family) + status.** Template landed: **R5a statistics**
> (`53eb5ff`) → `GET /api/v1/activity/statistics`. Each family: (1) add the
> `/api/v1` endpoint (`Endpoints.php` + handler method; a POST handler for
> multipart), (2) repoint the frontend api file from `basePath()` fetch →
> `apiGet`/`apiPost` (so a remote server authenticates by **bearer token** — the
> old same-origin **cookie** routes can't work cross-origin in the app), (3)
> delete the top-level route + controller method, (4) fix tests (controller test,
> `RoutesTest` row, `BundleCutoverTest::newApiEndpointsProvider`), (5) gate
> **including vitest** — R2 skipped vitest and a deleted-module test import
> slipped through (`c60aed9`); always run vitest when touching `src/frontend`.
>
> Families: **[x] statistics** · **[x] starter-vocab** (config GET `18f6e51` +
> import/enrich POST — the import/enrich endpoints are shared with word-upload's
> Frequency tab, both islands repointed to `apiPostForm`) · **[x] word-upload**
> (`GET /api/v1/terms/upload/config` via `apiGet` + multipart `POST
> /api/v1/terms/upload` via the new `apiPostMultipart` client helper) ·
> **[x] feeds** (config GET → `/api/v1/feeds/*/config` via `apiGet`; the save was
> already on `/api/v1/feeds`; dead native POST /feeds/new|edit routes dropped) ·
> **[x] dict-import** (multipart `POST /api/v1/local-dictionaries/import` via
> `apiPostMultipart`; `processImport` void→JSON) · [ ] bulk-translate (behavior
> port: native full-page form POST renders HTML → needs JSON + result UI) ·
> [ ] terms-export (native form `POST /words` → fetch + blob download) · [ ] books
> (needs bundle book pages or link-router handling) · [ ] admin (navbar links +
> `app/settings.ts`'s `settings_api`).

---

## 3. Phase M — move the frontend into the app

`src/frontend/` is **self-contained** (verified: no import escapes it), so the
move is mechanical. Target lands in the app repo (proposed `lukaisu/webapp/`;
keeps it distinct from the legacy connect shell in `lukaisu/src/`).

1. **Move the source** (preserve history):
   ```bash
   # in lukaisu-server
   git mv src/frontend  ../lukaisu/webapp      # js/, css/, app/
   ```
   (Cross-repo `git mv` isn't atomic — in practice: `git mv` within a temp,
   `git format-patch`/`git am` or a subtree move to preserve history, or a plain
   copy + `git rm` if history on the frontend isn't precious. Decide at execution.)

2. **Move the build config** into the app: `vite.app.config.ts` (becomes the
   app's `vite build`), `svelte.config.js`, the frontend `tsconfig`, the frontend
   `tests/frontend/`, and the frontend eslint config. Rewrite the `@/@shared/
   @modules/@css` alias roots to the new location.

3. **Rewire the app commands** (`lukaisu/package.json`):
   | Command | Today | After |
   |---|---|---|
   | `build:webapp` | `cd ../lukaisu-server && npm run build:app` | **deleted** |
   | `pull:webapp` | copy `../lukaisu-server/dist-app` → `dist/` | **deleted** |
   | `build` | `tsc --noEmit && vite build` *(legacy connect shell)* | `vite build --config vite.app.config.ts` → `dist/` |
   | `sync` | `build:webapp && pull:webapp && cap sync android` | `npm run build && cap sync android` |
   | `apk:release` | `sync && gradlew assembleRelease` | *unchanged wrapper; now single-repo* |

   The legacy connect-shell entries (`src/main.ts`, `sync:connect-shell`,
   `apk:*:connect-shell`, `index.html`) can be deleted — the local-first bundle
   is the ship build.

4. **Move the frontend test/lint/type toolchain** into the app's `package.json`:
   `svelte`, `@sveltejs/vite-plugin-svelte`, `dexie`, `ts-fsrs`, `chart.js`,
   `lucide`, `@yaireo/tagify`, `bulma`, `vitest`, `svelte-check`,
   `eslint-plugin-svelte`, `purgecss`, etc. — everything the frontend build/test
   needs. (`@alpinejs/csp` does **not** move — it dies in Phase R.)

**M exit criterion:** `cd lukaisu && npm run apk:release` builds a signed APK
with **no `../lukaisu-server` in the loop**, and `npx vitest run` (frontend
tests, now in the app) is green.

---

## 4. Phase C — strip the frontend from the server

Once the app owns and builds the frontend and Phase R removed the last server
consumer:

- Delete `src/frontend/` (moved), `vite.config.ts`, `vite.app.config.ts`,
  `svelte.config.js`, `sw.ts`/service-worker build, `scripts/build-themes.js`,
  `scripts/generate-pwa-icons.js`.
- Remove frontend `package.json` scripts (`dev`, `build`, `build:app`,
  `build:themes`, `build:pwa-icons`, `build:all`, `check:svelte`, `e2e:app`,
  `cy:*`) and frontend devDeps (svelte, vite-plugin-svelte, dexie, chart.js,
  vitest-frontend, svelte-check, purgecss, cypress-app, …).
- Remove `ViteHelper` and the `dist/`-serving glue from `index.php` / `.htaccess`
  (Option A: entirely; Option B: keep only what the isolated admin needs).
- The server is now **PHP `/api/v1` + Python edge**, no browser UI (Option A) or
  a tiny isolated admin (Option B).

---

## 5. What the commands look like, before → after (summary)

**`lukaisu-server`**

| | Today | After Piece 2 |
|---|---|---|
| Frontend build | `build`, `build:app`, `build:all`, `build:themes` | **gone** |
| Frontend tests | `vitest` over `src/frontend`, `cypress app` | **gone** (moved to app) |
| Deps | `@alpinejs/csp`, dexie, svelte, chart.js, … | **removed** |
| Role | PHP full-stack + API + app bundle source | **headless API** (+ Python edge) |

**`lukaisu` (app)**

| | Today | After Piece 2 |
|---|---|---|
| Frontend source | none (pulled from server) | **owns `webapp/`** |
| Build | shells into `../lukaisu-server` | local `vite build` |
| `sync` | build-webapp + pull + cap sync | `vite build` + cap sync |
| F-Droid main catalog | needs a `lukaisu-server` submodule | **single-repo build** (submodule question dies) |

---

## 6. Risks & notes

- **Don't fork the frontend.** During R (and until M lands), the app keeps
  bundling from the server (submodule/sibling). Never create a second diverging
  copy — the move is a one-shot cut, not a copy.
- **Admin is the crux, not the reader.** The reading/learning UI is already
  bundled; the only genuinely server-bound UI is admin/auth (Job C). The §0
  decision is really "what happens to admin."
- **Not required for the own-repo F-Droid release.** The app builds today with
  both repos present; Piece 2 is about *architecture + the main-catalog build*,
  not about shipping. Sequence it when the server-UI retirement is worth doing,
  not under release pressure.
- **Coordinate across repos.** This edits both `lukaisu-server` and `lukaisu`;
  land Phase R first (server-only), then M/C as a coordinated cross-repo change.
- **CSS/themes travel with the frontend** — `build-themes.js` + `css/themes/`
  move to the app; the server keeps none.
