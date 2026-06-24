---
title: "Proposal: Modernise the database column names"
description: Drop the legacy LWT Hungarian-style column prefixes (WoText, Ti2WoID, LgName) in favour of plain, table-scoped snake_case columns, table by table, behind the layers that already insulate the API and the offline client.
---

# Proposal: Modernise the database column names

**Status:** Approved; implementation in progress (2026-06). Follow-on to the table
renames already shipped in 2026-01 (`textitems2 → word_occurrences`, `tags2 →
text_tags`, `newsfeeds → news_feeds`, …). Those renamed the *tables*; the *columns*
still carry the original LWT prefixes. The decisions below are settled: full sweep
of every table, **snake_case + table-scoped** columns, one migration + commit per
table, smallest-referenced table first.

## Problem

The tables now read clearly, but every column is still prefixed with a two-letter
table tag inherited from the SourceForge original:

| Table | Column today | What it means |
| --- | --- | --- |
| `words` | `WoText`, `WoStatus`, `WoTextLC` | word / status / lower-cased word |
| `word_occurrences` | `Ti2WoID`, `Ti2Text`, `Ti2Order` | "**T**ext**I**tems**2**" — the old table name, frozen into every column |
| `languages` | `LgName`, `LgRegexpWordCharacters` | language name / parser regex |
| `text_tags` | `T2ID`, `T2Text` | "tags**2**" — opaque even after the table was renamed |

The `Ti2` and `T2` prefixes are the worst: they encode *the table's former name*,
so the column names actively mislead now that the tables have sensible names. Even
this repo's own `docs-src/reference/database-schema.md` is stale because of it (it
still documents `archivedtexts`, `archtexttags`, `temptextitems`).

This is purely a *naming* problem — the shapes and relationships are fine.

## Why this is safe now (the key enabler)

A rename of this size sounds terrifying — `LgID` alone appears ~1,200 times across
~200 files. But the cryptic names are **already sealed behind three layers**, so
renaming the physical columns breaks **no external contract**:

1. **The REST API serialises clean keys.** Handlers return `'status'`, `'text'`,
   `'translation'` — never `WoStatus`. (`TermApiController`,
   `MySqlTermRepository::$columnMap`.) The mobile client and `/api/v1` consumers
   never see a raw column name.
2. **The offline on-device DB already uses clean fields.** `schema.ts` stores
   `status`, `textLc`, `woId`, `langId` — it mirrors the *meaning*, not the column
   spelling. **No offline migration is required**; the bundled app is untouched.
3. **Modern repositories carry a `$columnMap`.** `MySqlTermRepository`,
   `MySqlTextRepository`, `MySqlLanguageRepository` map a clean property name to the
   column (`'text' => 'WoText'`). For those modules a rename is **one line per
   column** — just change the right-hand side.

So this is an **internal sweep**: SQL strings and the raw-column references in
`src/backend` (the legacy layer that still hits columns directly). Nothing the
outside world or the offline client depends on changes.

## Proposed convention

Plain, table-scoped `snake_case`. The table already names the entity, so the column
should not repeat it.

- **Primary key** → `id` (was `WoID`, `TxID`, `LgID`).
- **Foreign key** → `<referenced_table_singular>_id`: `language_id`, `text_id`,
  `word_id`, `sentence_id`, `user_id`, `tag_id` (was `WoLgID`, `Ti2TxID`, `Ti2WoID`…).
- **Attributes** → drop the prefix, snake_case the rest: `WoText → text`,
  `WoTextLC → text_lc`, `WoStatusChanged → status_changed_at`,
  `LgRegexpWordCharacters → regexp_word_characters`.
- **Timestamps** → `created_at` / `updated_at` / `<event>_at`.

This lands the columns on the *same vocabulary the offline mirror and the API
already use* (`status`, `text`, `text_lc`, `language_id`) — snake_case on the SQL
side, camelCase on the TS side, bridged by the existing `$columnMap`. It does not
invent a third naming world.

### Reserved-word cautions

Three current columns become SQL reserved words once de-prefixed — handle explicitly:

- `SeOrder` / `Ti2Order` → **`position`** (not `order`).
- `StKey` → **`name`** (not `key`); `settings(name, user_id, value)`.
- `FlDate` → `date` is tolerable but prefer **`published_at`** for clarity.

### Representative mapping (high-traffic tables)

```text
words            WoID→id  WoUsID→user_id  WoLgID→language_id  WoText→text
                 WoTextLC→text_lc  WoLemma→lemma  WoLemmaLC→lemma_lc
                 WoStatus→status  WoTranslation→translation  WoRomanization→romanization
                 WoSentence→sentence  WoNotes→notes  WoWordCount→word_count
                 WoCreated→created_at  WoStatusChanged→status_changed_at
                 WoTodayScore / WoTomorrowScore / WoRandom → DROP (see FSRS note)

texts            TxID→id  TxUsID→user_id  TxLgID→language_id  TxTitle→title
                 TxText→text  TxAnnotatedText→annotated_text  TxAudioURI→audio_uri
                 TxSourceURI→source_uri  TxPosition→position
                 TxAudioPosition→audio_position  TxArchivedAt→archived_at

languages        LgID→id  LgUsID→user_id  LgName→name  LgDict1URI→dict1_uri
                 LgGoogleTranslateURI→translator_uri  LgTextSize→text_size
                 LgRegexpWordCharacters→regexp_word_characters
                 LgParserType→parser_type  LgRightToLeft→right_to_left  … (24 cols)

word_occurrences Ti2WoID→word_id  Ti2LgID→language_id  Ti2TxID→text_id
                 Ti2SeID→sentence_id  Ti2Order→position  Ti2WordCount→word_count
                 Ti2Text→text

sentences        SeID→id  SeLgID→language_id  SeTxID→text_id  SeOrder→position
                 SeText→text  SeFirstPos→first_pos
```

Smaller tables follow mechanically: `tags`(Tg)→`id,user_id,text,comment`;
`word_tag_map`(Wt)→`word_id,tag_id`; `text_tags`(T2)→`id,user_id,text,comment`;
`text_tag_map`(Tt)→`text_id,text_tag_id`; `news_feeds`(Nf), `feed_links`(Fl),
`books`(Bk), `activity_log`(Al), `whisper_jobs`(Wj), `users`(Us), `settings`(St),
and the `temp_*` tables.

## Migration strategy — table by table, least-referenced first

Each table is an independent, shippable, revertible unit: **one migration + one
code sweep + green gates**, then commit. Ordering from fewest references to most
keeps early stages small while the pattern is being proven, and never leaves the
tree half-renamed.

1. **Per table, the migration** does `ALTER TABLE … CHANGE COLUMN old new <type>`
   for each column (preserving type/charset), after dropping the FK constraints
   that name the old columns and re-adding them against the new names — exactly the
   shape the existing `rename_textitems2.sql` migration already uses, just at the
   column level. Idempotent guards (`information_schema` checks) as elsewhere.
2. **The code sweep** for the same table: modern modules change only their
   `$columnMap` right-hand side; legacy `src/backend` SQL strings and any raw
   `$row['WoText']` access get rewritten. The offline mirror needs **no change**.
3. **Gates**, every stage: `./vendor/bin/phpcs --standard=PSR12`,
   `./vendor/bin/psalm --threads=1`, `composer test:no-coverage`,
   `npm run typecheck && npm run lint && npx vitest run`, `npm run build:app`, and
   the offline E2E (which should stay green untouched, proving the contract held).

**Suggested order** (ascending reference weight):
`whisper_jobs` · `activity_log` · `books` · `feed_links` · `news_feeds` ·
`temp_*` · `sentences` · `text_tag_map` · `word_tag_map` · `tags` · `text_tags` ·
`word_occurrences` → then the big three `texts` · `words` · `languages` → finally
`users` · `settings`.

A **MySQL compatibility view** (old column names over the renamed table) is possible
for an ultra-cautious rollout, but it adds a parallel surface to maintain and
contradicts the goal; recommended **against**. The table-at-a-time sweep is simpler
and each step is already atomic.

## Bundle with related work

- **FSRS (#238).** `WoTodayScore`, `WoTomorrowScore`, `WoRandom` are slated for
  removal when Leitner scoring is replaced. **Do not rename them** — drop them in the
  FSRS migration instead. See [the FSRS proposal](./term-status-fsrs.md).
- **Type tidy-ups** worth folding into the relevant table's migration (not just
  renames — actual schema fixes):
  - `books.BkLgID` is `INT(11)` while `languages.LgID` is `TINYINT(3)`; the FK types
    should match (`language_id TINYINT UNSIGNED`).
  - `wordtags.WtWoID` is documented as `INT(11)` but `words.WoID` is `MEDIUMINT(8)`;
    align to the referenced PK.
  - A handful of tables are still `CHARSET=utf8`; finish the `utf8mb4` conversion
    while touching them.
- **Stale reference doc.** `docs-src/reference/database-schema.md` is already wrong
  (documents dropped tables). Regenerate it from the live schema at the end so it
  reflects the new names in one pass rather than per-stage.

## Decisions (settled 2026-06)

1. **Column case — `snake_case`, table-scoped.** SQL idiom, matches the API/offline
   vocabulary; bridged by the existing `$columnMap`.
2. **Foreign-key spelling — `language_id` / `text_id` / `word_id` / `user_id`.**
3. **Scope — every table**, smallest-referenced first, one migration + commit each.

### Implementation notes (discovered during the sweep)

- **Rename migrations are guarded + idempotent.** Each column is renamed only when
  the old name still exists; if both old and new exist (a fresh install where a
  later `ADD COLUMN IF NOT EXISTS <old>` re-added a stray), the old duplicate is
  dropped instead. `PREPARE`/`EXECUTE`/`DEALLOCATE` go on separate lines so the SQL
  file parser splits them (it runs one statement per `mysqli_query`).
- **Both paths are verified per table** against a real MariaDB: fresh-install
  (baseline + all migrations) ends with only the new names, and an upgrade (old
  schema + the new migration) renames in place with data and FKs preserved.
- **`baseline.sql` is the cumulative schema** for fresh installs and the test DB, so
  it is updated alongside each migration; `tests/setup_test_db.php` (which hard-codes
  FK/column SQL) and `db/seeds/*.sql` are updated in the same step.

## Verification

1. Green gates after **every** table (list above) — a red gate scopes the failure to
   one table's sweep.
2. A new test asserting **no `/api/v1` response body contains a raw column token**
   (`/\b(Wo|Tx|Lg|Se|Ti2|T2|Nf|Fl)[A-Z]/`) — locks the contract that lets this be
   internal-only, and guards future regressions.
3. Offline E2E stays green with **zero** offline-code changes — the proof that the
   rename never crossed the client boundary.
4. A schema snapshot (dump `information_schema.columns`) diffed before/after each
   stage so the migration's column set is exactly the intended rename — no drops, no
   type drift.
