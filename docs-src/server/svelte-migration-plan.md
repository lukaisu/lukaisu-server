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

- [ ] **5. Library (active texts)** — `library.html` · `textsGroupedApp`, `dropdownToggle` (`@modules/text`)
- [ ] **6. Archived texts** — `texts.html` · `archivedTextsGroupedApp` (`@modules/text`) — sibling of #5; likely a shared component
- [ ] **7. Home / dashboard** — `home.html` · `homeApp`, `discoverBooks`, `gutenbergSuggestions`, `gdlSuggestions`, streak/`calendarHeatmap` (`home/`)

### Tier 3 — feeds, languages, auth, print

- [ ] **8. Feeds** — `feeds.html` · feed-manager SPA: `feedList`/`feedFilter`/`articleList`/`articleFilter`/`feedForm`/`feedNotifications` (+ `feedBrowse`/`feedLoader`/`feedWizard*`) (`@modules/feed`) — big, server-gated; consider splitting manage / browse / wizard
- [ ] **9. Languages list** — `languages.html` · `languageList` (`@modules/language`)
- [ ] **10. Connect / auth** — `index.html` · `clientAuth`, `registerForm`, `resetPasswordForm` (`@modules/auth`)
- [ ] **11. Text print** — `text-print.html` · `textPrintApp` (`@modules/text`) — small

### Tier 4 — shared chrome (cross-cutting; on every page)

`main.ts` imports these and runs `Alpine.start()` + `mountNavbar()` on every page,
so they render even on the plain-DOM pages. **Alpine cannot be removed until these
are Svelte.** Migrate carefully (a regression hits every screen).

- [ ] **12. Navbar + streak** — `navbar`, `navbarStreak`, `streakDisplay` (`shared/components`)
- [ ] **13. Footer / theme / offline / select** — `footer`, `themeToggle`, `offlineIndicator`, `offlineButton`, `searchableSelect` (`shared/components`, `shared/offline`)

### Tier 5 — optional consistency (no Alpine to remove)

These app pages are **already plain-DOM purpose-built API forms** — no Alpine.
Converting them to Svelte is consistency polish, not Alpine removal; do only if
wanted: `word`, `language`, `text`, `language-edit`, `text-edit`, `tags`,
`dictionaries`, `settings`, `text-check`.

### Final — remove Alpine

- [ ] **14. Retire Alpine** — once no `x-data` remains in any app page and the
  shared chrome (Tier 4) is Svelte: drop `@alpinejs/csp` + the vite alias, remove
  the CSP-build workarounds, delete jQuery (`jq_pgm.ts`). Retire the now-dead
  PWA-only Alpine components (`word_list_app.ts`, etc.) at the **PWA cut-over**.

## Notes for the loop

- **Pick the next unchecked division each pass**, port it, run the verify gate,
  commit, check it off here.
- **Split when too big.** The reader (#2/#3) and feeds (#8) are larger than the
  word list; a pass may take a sub-screen and leave the rest unchecked.
- **Don't fork the data layer or the frontend.** Build in
  `lukaisu-server/src/frontend/`; coordinate the eventual relocation to `lukaisu`.
- **Per-component count for sizing:** feed 11, vocabulary 10, admin 7 (PWA-mostly),
  text 6, auth 3 — see `grep -rn "Alpine.data(" src/frontend/js`.
