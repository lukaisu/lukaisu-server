---
title: "Proposal: Term Status Model + FSRS Scheduling"
description: Centralize the scattered word-status model into a single source of truth, and align review scheduling with Anki/FSRS by separating display familiarity from memory state.
---

# Proposal: Term Status Model + FSRS Scheduling

**Status:** Phase 1 implemented (2026-06); Phase 2 (FSRS) still proposed.
Tracked in [issue #238](https://github.com/lukaisu/lukaisu-server/issues/238).

**Phase 1 — done.** `TermStatus` is now the authoritative display model: it holds
`abbreviation`/`cssClass`/`colourHex`/`order` plus the predicates, exposes the
complete table via `TermStatus::definitions()` (served at `GET /api/v1/statuses`),
and `TermStatus::isValid()`/`all()` replace the scattered `[1,2,3,4,5,98,99]`
literals across the PHP handlers. On the frontend, `shared/stores/statuses.ts` is
the single source the reading view, charts, popover and edit modal read from —
the six duplicated per-file tables are gone (which also fixed the admin chart's
mislabel of status 1 as "Unknown"). The store ships the table built-in so the
bundled offline app needs no server. `TermStatusService`/`StatusHelper` stay as
thin facades over the value object rather than being folded in wholesale (lower
risk, same single-source result).

**Phase 2 — FSRS scheduling — still proposed** (the architectural part below). It
has open decisions that need a maintainer's call before implementation; see
*Trade-offs & open questions*.

## Problem

The word-status model — `1-5` (learning), `98` (ignored), `99` (well-known) — is the
spine of both the reading UI (word colouring) and the review system, yet it is
modelled ad-hoc:

- **Duplicated everywhere.** The literal `[1,2,3,4,5,98,99]` and checks like
  `$status === 5 || $status === 99` ("known") recur across **11+ PHP files**
  (`WordFamilyService`, `StatusHelper`, `ReviewApiHandler`, `MySqlStatisticsRepository`,
  `SubmitAnswer`…). Label/colour/order/CSS tables are **re-defined in ~6 TS files**
  (`word_popover.ts`, `term_edit_modal.ts`, `text_status_chart.ts`,
  `texts_grouped_app.ts`, `html_utils.ts`, `statistics_charts.ts`). A
  `TermStatus` value object already exists
  (`src/Modules/Vocabulary/Domain/ValueObject/TermStatus.php`) but is not the single
  source of truth.
- **Scheduling is a hand-tuned Leitner curve.** "Due-ness" comes from
  `TermStatusService::SCORE_FORMULA_TODAY/TOMORROW`: a per-status linear decay
  (`base(status) − decay(status) × days_since_status_change`, clamped at −125, status
  > 5 ⇒ 100) stored in `WoTodayScore`/`WoTomorrowScore`, shuffled by `WoRandom`. The
  status *is* the box; a review just nudges status ±1
  (`SubmitAnswer::executeWithChange`). There is no real memory model, no per-term
  difficulty, no retention target, and no review history.

So two distinct concerns are conflated in one integer: **how familiar a word is**
(needed by the reading view) and **when it should next be reviewed** (scheduling).

## Goal

1. **Make the status model a single source of truth** (foundational, low-risk).
2. **Align scheduling with Anki/FSRS** by separating *display familiarity* from
   *memory state*, replacing the Leitner score formulas with a principled scheduler.

## Phase 1 — Status as a single source of truth

- Promote `TermStatus` (value object) to the authoritative model: hold `value`,
  `label`, `abbreviation`, `cssClass`, `colourHex`, `order`, and predicates
  (`isKnown()`, `isIgnored()`, `isLearning()`). Fold `TermStatusService`'s scattered
  helpers and `StatusHelper`'s magic-range arithmetic into it.
- Replace the 11+ PHP literal arrays / inline comparisons with the value object.
- Expose it **once** to the frontend (bootstrap payload or
  `GET /api/v1/settings/status-definitions`); delete the ~6 duplicated TS tables in
  favour of a single `shared/stores/statuses.ts`.

This stands alone and is worth doing regardless of Phase 2.

## Phase 2 — FSRS-aligned scheduling

### The core idea: split the two concerns

| Concern | Today | Proposed |
| --- | --- | --- |
| **Display familiarity** (reading colours) | `WoStatus` 1–5/98/99 | keep 1–5/98/99 — but *derive* 1–5 from memory strength |
| **Scheduling** (when to review) | per-status decay score | FSRS memory state per term |

Anki/FSRS models each item's memory with three quantities:

- **Stability (S)** — days for retrievability to fall to 90%.
- **Difficulty (D)** — how hard the item is (≈1–10).
- **Retrievability (R)** — current recall probability, from the power forgetting
  curve `R(t) = (1 + F · t/S)^D_curve` (constants `F`, `D_curve` come from the FSRS
  spec/optimizer). The item becomes due when `R` drops to a **target retention**
  (default 0.9).

Reviews are graded on **4 buttons** — Again / Hard / Good / Easy — and each grade
updates `S` and `D` via the FSRS update functions, yielding the next due date.

### What changes

1. **Schema** — add per-term scheduling state (new columns or a `term_schedule`
   table keyed by `WoID`): `stability`, `difficulty`, `due`, `last_review`, `reps`,
   `lapses`, `state` (new/learning/review/relearning). Retire
   `WoTodayScore`/`WoTomorrowScore`/`WoRandom` and the SQL score formulas.
2. **A `Scheduler` service** (in `Modules/Review`) implementing the FSRS update +
   next-interval computation, behind an interface so the algorithm is swappable
   (FSRS now, room for SM-2/custom later). The open-source FSRS reference
   (`open-spaced-repetition`, permissively licensed) is ~a few hundred lines to port;
   verify whether a maintained PHP port can be vendored instead of hand-porting.
3. **Review UX** — the binary correct/incorrect (± 1 status) becomes the 4-grade
   rating. `SubmitAnswer` calls the scheduler instead of `calculateNewStatus`.
4. **`review_log` table** — record `(WoID, grade, state, S, D, elapsed, reviewed_at)`
   per review. FSRS can schedule from current state alone, but logs are required to
   later **optimise** the FSRS parameters per user (Anki's "FSRS optimizer").
5. **Derive display status from stability** — bucket `S` into the familiar 1–5 tiers
   (e.g. `S<1d⇒1`, `<7d⇒2`, `<30d⇒3`, `<90d⇒4`, `≥90d⇒5`) so reading colours reflect
   real memory strength. `98`/`99` stay manual flags meaning "ignored" / "known, not
   scheduled" (≈ Anki suspended). Keep a manual status override that seeds `S`/`D`.

### Migration / continuity

Existing terms have only `WoStatus` + `WoStatusChanged`. Seed FSRS state from them:
map each status to a starting `S` (reuse the current per-status intervals as the
seed), set a default `D`, and `last_review = WoStatusChanged`. No review history is
lost because there is none today; the `review_log` starts accumulating from rollout.

## Trade-offs & open questions

- **Display status: derived vs. manual. — DECIDED (2026-06): derive from
  stability, with a manual override.** Bucket `S` into the 1–5 tiers so reading
  colours track real memory strength; a manual status set still pins/overrides
  `S`/`D`. `98`/`99` stay manual flags.
- **Review grading. — DECIDED (2026-06): ship the full 4-grade scale**
  (Again / Hard / Good / Easy). No 2-button mode for now (it can be added later
  if users ask).
- **Per-user vs. global parameters.** FSRS ships sensible defaults; per-user
  optimisation needs enough `review_log` history and an optimiser job (defer).
- **Offline mirror.** Scheduling runs on-device in the bundled app, so the
  `Scheduler` must be ported to TS alongside the PHP one (like the parsers), with
  the schema mirrored in the on-device DB. The FSRS update is pure arithmetic —
  no server needed — so this fits the local-first seam.
- **Scope.** Phase 2 touches schema, the Review module, the review UI, stats, and
  the offline client. Phase 1 has landed; Phase 2 is worth its own dedicated PR.
- **Licensing — still open.** Confirm the chosen FSRS implementation's licence
  before vendoring vs. hand-porting `open-spaced-repetition` (the one remaining
  pre-implementation question).

## Scope sketch (when picked up)

- **Phase 1:** `TermStatus` VO (expand), `TermStatusService` + `StatusHelper`
  (fold in), ~11 PHP call sites (adopt VO), status-definitions API + bootstrap, ~6 TS
  files → `shared/stores/statuses.ts`.
- **Phase 2:** migration (schedule columns / `term_schedule` + `review_log`),
  `Scheduler` interface + FSRS implementation, `Review/Application/UseCases/SubmitAnswer`
  (call scheduler), review UI (4-grade), stats that read `WoTodayScore` → read `due`,
  removal of `SCORE_FORMULA_*` and `WoRandom`.

## Verification (at implementation time)

1. Unit-test the FSRS `Scheduler` against the reference implementation's known
   vectors (same `S`/`D`/grade in → same interval out).
2. PHP + frontend gates (`phpcs`, `psalm`, `composer test:no-coverage`, `typecheck`,
   `lint`, `test`, `build:all`).
3. Migration round-trip on a seeded DB: every pre-existing term gets valid FSRS state;
   reading-view colours are stable immediately after migration.
4. E2E: run a review session, grade across all 4 buttons, confirm due dates advance
   sensibly and the reading view reflects status changes.
