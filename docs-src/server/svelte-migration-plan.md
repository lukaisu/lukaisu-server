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
