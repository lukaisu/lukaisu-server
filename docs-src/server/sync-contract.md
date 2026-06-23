# Multi-Device Sync — Design Contract

> **Status: DESIGN ONLY.** Sync is **not** part of the F-Droid milestone (which
> is "the app works with no server"). This document specifies the data model and
> conflict strategy **before** any sync code is written, as the briefing
> requires. Do not implement against it until the local-first client DB exists
> and this design has been spiked in isolation.

## The problem

After the [local-first migration](./local-first.md), the **client owns the
database** (an on-device store). A user with two devices — phone and tablet — who
opts into a server expects their languages, texts, words, review schedule, tags,
feeds, and settings to converge on both. Devices edit **offline** and reconcile
later, so this is a distributed-state problem, not a CRUD-over-HTTP problem.

Sync is the underestimated monster of this project. The failure modes are: lost
offline edits, resurrected deletes, duplicated rows after a device reinstall,
and divergent review schedules. The design below is chosen to make those modes
either impossible or explicit.

## What syncs, and what doesn't

The schema splits cleanly into **authored state** (syncs) and **derived state**
(never syncs — regenerated on-device).

| Syncs (authored, user-scoped) | Does **not** sync (derived / regenerated) |
|---|---|
| `languages` | `sentences` — reparsed from `texts.TxText` |
| `texts` (incl. `TxArchivedAt` soft-delete, reading/audio position) | `word_occurrences` (`textitems2`) — reparsed |
| `words` (status 1–5/98/99, translation, notes, lemma, SRS scores) | `temp_*` parsing scratch tables |
| `tags`, `text_tags` and their join maps | `feed_links` (re-fetchable from the feed; optional cache) |
| `news_feeds` (subscriptions) | server-side NLP/job state (`whisper_jobs`) |
| `books`, `local_dictionaries` (+ entries) | |
| `settings` (per-user) | |
| `activity_log` (counters, mergeable) | |

**Rule:** anything reconstructable from authored state by running the parser is
derived. Syncing only authored state keeps payloads small and sidesteps most
ordering hazards (you never have to sync a word-occurrence whose parent text
hasn't arrived yet — the client reparses after the text lands).

## Identity: global IDs, not autoincrement

The current schema uses small server-assigned autoincrement PKs (`LgID` is a
`tinyint`, `TxID` a `smallint`, `WoID` a `mediumint`). **These cannot be the
sync identity** — two offline devices would both mint `WoID = 51` for different
words and collide on merge.

**Decision: every syncable row gets a globally-unique `sync_id` (a
[ULID](https://github.com/ulid/spec) — sortable, 128-bit, collision-safe across
devices).** Local autoincrement PKs stay as-is for on-device FKs and query
performance; `sync_id` is the identity used *over the wire* and in the server
store. Foreign keys are carried in payloads as the **parent's `sync_id`**, so a
receiving device resolves them to its own local PKs. This avoids a fragile
"renumber every FK on import" pass.

## Conflict strategy: per-row Last-Writer-Wins via Hybrid Logical Clocks

Three options were considered:

| Option | Verdict |
|---|---|
| **Server-authoritative-when-online** (server state always wins) | ❌ Silently discards offline edits — unacceptable for an offline-first reader. |
| **CRDTs** (per-field convergent types) | ❌ Overkill. This is single-user multi-device, not real-time collaboration. The cost (per-field metadata, merge logic for every column) isn't justified. Revisit only if true collaborative editing is ever a goal. |
| **Per-row LWW with Hybrid Logical Clocks (HLC)** | ✅ **Chosen.** Simple, offline-correct, and deterministic. Each row carries an HLC timestamp; the higher HLC wins. HLCs ([physical time + logical counter + node id]) give a total order that survives clock skew, which plain wall-clock `updated_at` does not. |

LWW resolves the *vast* majority of cases (a row is usually edited on one device
at a time). For the handful of fields where blind LWW is lossy, apply targeted
merges **on top of** LWW:

- **`activity_log`** counters: merge by `MAX` per day (or sum of per-device
  deltas), never overwrite — these are monotonic aggregates.
- **Word SRS scores** (`WoTodayScore`/`WoTomorrowScore`/review state): prefer the
  row with the most-recent *review event* HLC, not just any edit, so a metadata
  tweak on device B doesn't clobber a review done on device A.
- **`texts` reading position**: LWW is fine (last device read wins); it's
  low-stakes.

### Deletes are tombstones, not row removal

A hard delete can't propagate (there's no row left to send). Every syncable
table gets a soft-delete tombstone: `deleted` flag + the HLC of the deletion.
A delete is just a write with `deleted = true`; LWW then correctly orders
delete-vs-edit (a later edit resurrects, a later delete wins). Tombstones are
retained for a bounded window (e.g. 90 days) then garbage-collected, which is
safe once all known devices have synced past them.

## Required schema additions (sync metadata)

Every syncable table needs four columns. The
[DB schema audit](../reference/database-schema.md) found most tables **lack**
them today (only `books` is fully ready: it already has `BkCreated`/`BkUpdated`).

| Column | Type | Purpose |
|---|---|---|
| `*_sync_id` | `CHAR(26)` (ULID) | global identity, unique per `(user, table)` |
| `*_updated_hlc` | `VARCHAR(40)` | Hybrid Logical Clock of last write — the LWW key |
| `*_deleted` | `TINYINT(1)` | tombstone flag |
| `*_origin` | `VARCHAR(40)` | device id that authored the last write (debugging, GC) |

Tables needing these added (from the audit): `languages`, `texts` (has
`TxArchivedAt` already — keep it as domain state, separate from `*_deleted`),
`words` (has `WoCreated`/`WoStatusChanged` — neither is a general LWW clock),
`tags`, `text_tags` + join maps, `news_feeds`, `settings`,
`local_dictionaries`(+entries), `activity_log`. The migrations live under
`db/migrations/` and must backfill `sync_id`/HLC for existing rows on first run.

## Transport

A pull/push delta protocol against the edge service (new `/sync` router), keyed
by an opaque per-device **cursor** (the highest HLC the device has durably
applied).

```
POST /sync/push        Auth: Bearer <token>
  body:  { "device_id": "...", "since": "<cursor>",
           "ops": [ { "table": "words", "sync_id": "...", "hlc": "...",
                      "deleted": false, "fields": { ... },
                      "parents": { "WoLgID": "<language sync_id>" } }, ... ] }
  reply: { "applied": <n>, "conflicts": [ ... resolved-as ... ] }

GET  /sync/pull?since=<cursor>&limit=500     Auth: Bearer <token>
  reply: { "ops": [ ...same op shape... ], "cursor": "<new cursor>", "more": true|false }
```

Properties:

- **Client pending-op queue.** Local writes append an op (table, sync_id, HLC,
  field snapshot, tombstone) to a durable queue. Push drains it; pull applies
  remote ops. The queue is the source of truth for "what hasn't synced yet" and
  survives app restarts.
- **Idempotent.** Ops are keyed by `(sync_id, hlc)`; re-delivering one is a
  no-op. A push interrupted mid-flight is simply retried.
- **Ordered by HLC, batched.** The server applies LWW per row and returns its
  authoritative view so a client that pushed a now-stale write learns it lost.
- **Parents-by-sync-id.** FKs travel as parent `sync_id`s; a receiver buffers an
  op whose parent it hasn't seen yet and applies it once the parent arrives
  (or, since only authored state syncs, ordering is mostly naturally satisfied).
- **Derived state is local.** After applying text/word ops, the client reparses
  to rebuild `sentences`/`word_occurrences`. None of that crosses the wire.

## Security & scoping

Sync **requires auth** (it's the first thing that does — see [auth.md](./auth.md)).
The server enforces that every op's row is scoped to the authenticated user
(`*UsID`); a device may only push/pull its own user's data. ULIDs are
unguessable but are **not** an authorization boundary — the `user_id` check is.

## Open questions to resolve during the spike

1. **Client DB engine & schema** — does the on-device store mirror the MySQL
   column names or use its own? The op `fields` shape depends on this. Coordinate
   with the client agent.
2. **HLC node id** = `device_id`; confirm devices get a stable id at install.
3. **Settings granularity** — sync all per-user settings, or a curated subset
   (some are device-local, e.g. theme)? Lean toward a per-key `sync: bool`.
4. **First-sync of a populated server** (existing self-hoster turns on a second
   device) — bulk-pull path vs. op-by-op. Probably a snapshot endpoint.
5. **Tombstone GC window** vs. worst-case device-offline duration.

## Phasing

1. (now) Land the four sync-metadata columns as **nullable, unused** migrations
   so future sync doesn't require a flag-day schema change. *Still design-time —
   do not ship until the client DB is real.*
2. Spike HLC + LWW + tombstones on `words` only, in isolation, with a two-fake-
   device test harness.
3. Generalize to all authored tables; add the `/sync` router and auth.
4. Snapshot/bootstrap path; tombstone GC; field-level merges for the special
   cases above.

Sync is explicitly **out of scope** until the local-first client exists. This
document is the contract it will be built to.
