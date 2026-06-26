# Local-First Migration

Lukaisu Server is being turned from a full-stack PHP app (a fork of Learning
with Texts) into an **optional, Python-first server**. This page is the
map: where we're going, the rule that gets us there, and where we are now.

## The goal

**A Lukaisu app installed from F-Droid must work with NO server.** A first-time
user installs the app and — fully offline — can create a language, paste/import a
text, read it with word-status highlighting, save words, and review them.

That milestone is **mostly client-side work**. On the server side the job is to
make the server **optional, not required**, and to make sure that when a server
*is* connected it is the **Python edge** doing the few things a phone can't:
CJK tokenization, lemmatization, TTS, transcription, and outbound content
fetches.

"Server optional" means precisely: **nothing on the critical read/learn path for
space-separated and right-to-left languages may require a server.** CJK,
content discovery, TTS, and transcription are "enhanced-when-connected" — not
part of the no-server milestone.

## The rule

> **Freeze PHP. Build Python.** Do not add new PHP features. The PHP backend is
> scaffolding to be progressively hollowed out: rendering moves to the client,
> data/DB moves to the client, and what remains on the server is what genuinely
> cannot run on a phone.

PHP stays **runnable** as the legacy full-stack server for existing self-hosters
during the transition. It just stops growing — every new capability lands in the
client (TypeScript) or the [edge service](../../services/nlp/README.md) (Python).

## The four-bucket seam

Every capability is one of these. This is the contract the server and client
agents both build to.

| Bucket | Owner after migration | Examples |
|---|---|---|
| **Rendering** | **Client** (TypeScript) | reader, review surface, popups, navbar, i18n |
| **Data / DB** | **Client** (on-device DB) | languages, texts, words, sentences, occurrences, tags, settings, review scheduling |
| **NLP** | **Optional Python edge** | CJK parse (MeCab/jieba), lemmatize (spaCy), TTS (Piper), Whisper |
| **Outbound / network** | **Optional Python edge** | Gutendex, GDL, Internet Archive, RSS, YouTube, web/EPUB extraction |

### Parsers: the degradation rule

Two parsers are **pure PHP today and are being ported to TypeScript by the
client** so they run with no server:

- `RegexParser` — space-separated + RTL languages. **The critical read path.**
- `CharacterParser` — CJK char-by-char fallback (functional, lower quality).

With no server, CJK falls back to the character parser. When the edge is
connected, CJK uses the Python tokenizer (MeCab/jieba). **The client must never
block on the server** — if the edge is absent or a capability is unavailable, it
takes the on-device path. The edge advertises what it can do via
`GET /capabilities`.

The PHP parsers under `src/Modules/Language/Infrastructure/Parser/` remain the
reference implementation the client ports.

## Where we are now

| Step | State |
|---|---|
| 1. NLP service stands alone (callable without PHP, CORS, documented contract) | ✅ **Done.** The [edge service](../../services/nlp/README.md) boots standalone, enables CORS, mounts every feature group defensively, and exposes `/capabilities`. Contract: [http-contract.md](./http-contract.md). |
| 2. Python edge for outbound work (content, RSS, web/EPUB/YouTube) | ✅ **Done.** New `/content`, `/feeds`, `/extract` routers port the PHP clients (`GutenbergClient`, `GdlClient`, `RssParser`, `WebPageExtractor`, `EpubParserService`, `YouTubeApiHandler`) plus a new Internet Archive source — all behind one SSRF-safe fetch util. |
| 3. Sync contract (design first) | 📐 **Designed, not built.** See [sync-contract.md](./sync-contract.md). |
| 4. Auth (only where sync needs it) | 📐 **Design note only.** See [auth.md](./auth.md). |
| 5. Freeze + shrink PHP | ♻️ **Ongoing policy.** PHP stays as the legacy server; no new features. |

## What's out of scope (for the F-Droid milestone)

- Implementing sync (design the contract only).
- Multi-user auth on the Python server (until sync exists).
- Rewriting CRUD in Python (CRUD moves to the **client**, not to Python).
- Decommissioning PHP (it stays as the legacy server during transition).

## For contributors

- Touching NLP or outbound integrations? Work in `services/nlp/` (Python), not
  the corresponding PHP. Keep the [HTTP contract](./http-contract.md) stable and
  route every outbound fetch through `app/services/http/safe_fetch.py`.
- Adding a reader/data feature? It belongs in the **client**, not here.
- The `src/frontend/` TypeScript currently lives in this repo and is bundled
  into the app ("Model B"). Its long-term home is the `lukaisu` app — coordinate
  with the client agent and **don't fork the frontend in two places**.

---

*Paired with `lukaisu/BRIEFING.md` (the client track). Keep the shared "Goal"
and "Seam" sections in sync if either changes.*
