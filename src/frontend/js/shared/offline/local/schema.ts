/**
 * Local-first database schema (Dexie / IndexedDB).
 *
 * This mirrors the server's MySQL schema (`db/schema/baseline.sql`) closely
 * enough that the same rendering code can read it, but is single-user: the
 * `*UsID` ownership columns are dropped. Every row carries `createdAt` /
 * `updatedAt` (epoch ms) and a `deletedAt` tombstone so a sync layer can be
 * added later without a migration; a `pendingOps` queue is reserved for the
 * same purpose. Sync itself is out of scope for the offline milestone.
 *
 * This is the local-FIRST store (`LukaisuLocalDB`), distinct from the older
 * download-for-offline cache prototype in `../db.ts` (`LukaisuOfflineDB`).
 *
 * @license Unlicense <http://unlicense.org/>
 */

import Dexie, { type Table } from 'dexie';

/** Word status values (mirrors the server `WoStatus`). */
export const WordStatus = {
  /** Unknown — not stored as a row; rendered when an occurrence has no word. */
  UNKNOWN: 0,
  NEW: 1,
  LEARNING_2: 2,
  LEARNING_3: 3,
  LEARNING_4: 4,
  LEARNED: 5,
  IGNORED: 98,
  WELL_KNOWN: 99,
} as const;

/** A language and its parsing/display configuration (`Lg*`). */
export interface LocalLanguage {
  id?: number;
  name: string;
  /** Source language code being learned, e.g. `fr`. */
  code: string;
  dict1Uri: string;
  dict2Uri: string;
  translatorUri: string;
  exportTemplate: string;
  textSize: number;
  characterSubstitutions: string;
  regexpSplitSentences: string;
  exceptionsSplitSentences: string;
  regexpWordCharacters: string;
  removeSpaces: boolean;
  splitEachChar: boolean;
  rightToLeft: boolean;
  ttsVoiceApi: string;
  showRomanization: boolean;
  createdAt: number;
  updatedAt: number;
  deletedAt: number | null;
}

/** A text the user reads (`Tx*`). */
export interface LocalText {
  id?: number;
  langId: number;
  title: string;
  /** Raw source text (original, unsplit). */
  text: string;
  audioUri: string | null;
  sourceUri: string | null;
  /** Current reading position (occurrence order), 0 if unread. */
  position: number;
  audioPosition: number;
  /** Soft archive timestamp (epoch ms), null if active. */
  archivedAt: number | null;
  createdAt: number;
  updatedAt: number;
  deletedAt: number | null;
}

/** A learned/known term (`Wo*`). */
export interface LocalWord {
  id?: number;
  langId: number;
  /** Original surface form (case preserved). */
  text: string;
  /** Lower-cased form; unique per language; used for matching occurrences. */
  textLc: string;
  lemma: string;
  lemmaLc: string;
  status: number;
  translation: string;
  romanization: string;
  /** Example sentence with the term marked as `{term}`. */
  sentence: string;
  notes: string;
  /** 0 = single word; >=1 = multi-word expression spanning N tokens. */
  wordCount: number;
  created: number;
  /** When `status` last changed (drives review scoring). */
  statusChanged: number;
  todayScore: number;
  tomorrowScore: number;
  random: number;
  updatedAt: number;
  deletedAt: number | null;
}

/** A sentence within a text (`Se*`). */
export interface LocalSentence {
  id?: number;
  langId: number;
  textId: number;
  /** 0-based sentence index within the text. */
  order: number;
  text: string;
  /** Occurrence order of the first token in this sentence. */
  firstPos: number;
}

/**
 * A text position mapped to a (possibly unknown) word (`Ti2*`).
 *
 * `woId` is null for unknown words and for non-word tokens. `isWord`
 * distinguishes learnable tokens from punctuation/whitespace.
 */
export interface LocalOccurrence {
  id?: number;
  textId: number;
  langId: number;
  sentenceId: number;
  /** 0-based position within the text (token order). */
  order: number;
  /** 0 = single token; >=1 = part of a multi-word expression. */
  wordCount: number;
  /** The token text as it appears in the source. */
  text: string;
  /** Lower-cased token text (for matching against words). */
  textLc: string;
  isWord: boolean;
  /** Linked word id, or null when unknown / non-word. */
  woId: number | null;
}

/** A vocabulary tag (`Tg*`). */
export interface LocalTag {
  id?: number;
  text: string;
  comment: string;
}

/** Word⇄tag mapping (`Wt*`). */
export interface LocalWordTag {
  id?: number;
  woId: number;
  tgId: number;
}

/** A text tag / category (`T2*`). */
export interface LocalTextTag {
  id?: number;
  text: string;
  comment: string;
}

/** Text⇄text-tag mapping (`Tt*`). */
export interface LocalTextTagMap {
  id?: number;
  txId: number;
  t2Id: number;
}

/** A single setting (`settings`), single-user so keyed by `key` alone. */
export interface LocalSetting {
  key: string;
  value: string;
}

/** A queued local mutation for a future sync layer. */
export interface LocalPendingOp {
  id?: number;
  type: string;
  entity: string;
  entityId: number;
  data: Record<string, unknown>;
  createdAt: number;
  retries: number;
}

/**
 * The local-first database. Index strings list only the indexed fields; other
 * fields are stored but not indexed.
 */
export class LukaisuLocalDatabase extends Dexie {
  languages!: Table<LocalLanguage, number>;
  texts!: Table<LocalText, number>;
  words!: Table<LocalWord, number>;
  sentences!: Table<LocalSentence, number>;
  occurrences!: Table<LocalOccurrence, number>;
  tags!: Table<LocalTag, number>;
  wordTags!: Table<LocalWordTag, number>;
  textTags!: Table<LocalTextTag, number>;
  textTagMap!: Table<LocalTextTagMap, number>;
  settings!: Table<LocalSetting, string>;
  pendingOps!: Table<LocalPendingOp, number>;

  constructor(name = 'LukaisuLocalDB') {
    super(name);
    this.version(1).stores({
      languages: '++id, name, code, deletedAt',
      texts: '++id, langId, archivedAt, deletedAt, updatedAt',
      words:
        '++id, langId, &[textLc+langId], status, todayScore, tomorrowScore, random, lemmaLc, deletedAt, updatedAt',
      sentences: '++id, textId, langId, [textId+order]',
      occurrences:
        '++id, textId, langId, sentenceId, [textId+order], [langId+textLc], woId, isWord',
      tags: '++id, &text',
      wordTags: '++id, woId, tgId, [woId+tgId]',
      textTags: '++id, &text',
      textTagMap: '++id, txId, t2Id, [txId+t2Id]',
      settings: 'key',
      pendingOps: '++id, entity, type, createdAt',
    });
  }
}

/** Singleton instance used by the repositories. */
export const localDb = new LukaisuLocalDatabase();

/** True if IndexedDB is usable in this environment. */
export function isIndexedDbAvailable(): boolean {
  try {
    return typeof indexedDB !== 'undefined' && indexedDB !== null;
  } catch {
    return false;
  }
}
