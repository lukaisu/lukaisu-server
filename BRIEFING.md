# Lukaisu Server — Local-First Migration Briefing

> **Audience:** the implementation agent working in this repo (`lukaisu-server`).
> **Paired doc:** `lukaisu/BRIEFING.md` (the client). Read the shared sections
> (Goal, Seam) — they are identical in both so the two tracks compose.

## Mission

Turn this PHP backend (a rebranded fork of Learning with Texts 3.2.0) into an
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
| **Rendering** | **Client** (already TS) | reader, review surface, word popups, navbar, i18n — already in `src/frontend/`, bundled into the app via "Model B" |
| **Data / DB** | **Client** (on-device DB) | languages, texts, words/terms, sentences, word-occurrences, tags, settings, review scheduling |
| **NLP** | **Optional server (Python)** | CJK parse (MeCab/jieba), lemmatization (spaCy), TTS (Piper), Whisper transcription |
| **Outbound / network** | **Optional server (Python)** | Gutenberg/Gutendex, Global Digital Library, Internet Archive, RSS feeds, YouTube transcripts, arbitrary web/EPUB URL extraction |

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
- **Where does the frontend TS ultimately live?** Today `src/frontend/` is here
  and bundled into the app (Model B). The stated direction is rendering "moves
  to the client." Coordinate with the client agent: for the milestone it's fine
  to keep the TS here and bundle it; the long-term home is the `lukaisu` app.
  Don't fork the frontend in two places.
- **Python framework:** the NLP service appears to be FastAPI already — extend
  it rather than introducing a second framework.

---
*Paired with `lukaisu/BRIEFING.md`. Keep the shared "Goal" and "Seam" sections in
sync if either changes.*
