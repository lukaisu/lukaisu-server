/**
 * Text repository — on-device text import, reading, statistics and bulk
 * actions. This is where a raw text becomes sentences + word-occurrences via
 * the local tokenizer, freeing the read path from the server.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { localDb, type LocalLanguage, type LocalWord } from '../schema';
import { parseText } from '../parser';
import {
  buildStructures,
  toLocalOccurrences,
  assembleTextWords,
  buildReadingConfig,
} from '../text-assembly';
import {
  WordStatus,
} from '../schema';
import {
  buildWordRow,
  languageToParserConfig,
  nowMs,
} from './helpers';
import type {
  TextCreateRequest,
  TextCreateResponse,
  TextWordsResponse,
  TextStatistics,
  MarkAllResponse,
  MarkedWordData,
} from '@modules/text/api/texts_api';
import { toClassName } from '../class-name';

/**
 * (Re)parse a text's body into sentences + occurrences, linking occurrences to
 * any existing words. Replaces previously stored structures for the text.
 */
async function storeParsedText(
  textId: number,
  language: LocalLanguage,
  rawText: string
): Promise<void> {
  const langId = language.id ?? 0;
  const parse = parseText(rawText, languageToParserConfig(language));
  const built = buildStructures(parse);

  const words = await localDb.words
    .where('langId')
    .equals(langId)
    .and((w) => w.deletedAt == null)
    .toArray();
  const wordIdByTextLc = new Map<string, number>();
  for (const w of words) {
    if (w.id != null) {
      wordIdByTextLc.set(w.textLc, w.id);
    }
  }

  await localDb.transaction('rw', localDb.sentences, localDb.occurrences, async () => {
    await localDb.sentences.where('textId').equals(textId).delete();
    await localDb.occurrences.where('textId').equals(textId).delete();

    const sentenceIdByOrder = new Map<number, number>();
    for (const s of built.sentences) {
      const id = await localDb.sentences.add({
        langId,
        textId,
        order: s.order,
        text: s.text,
        firstPos: s.firstPos,
      });
      sentenceIdByOrder.set(s.order, id);
    }

    const occ = toLocalOccurrences(
      built.occurrences,
      textId,
      langId,
      sentenceIdByOrder,
      wordIdByTextLc
    );
    await localDb.occurrences.bulkAdd(occ);
  });
}

/** Create a text and parse it on-device. */
export async function createText(
  req: TextCreateRequest
): Promise<TextCreateResponse> {
  const language = await localDb.languages.get(req.langId);
  if (!language) {
    return { error: 'Language not found' };
  }
  const now = nowMs();
  const id = await localDb.texts.add({
    langId: req.langId,
    title: req.title,
    text: req.text,
    audioUri: req.audioUri ?? null,
    sourceUri: req.sourceUri ?? null,
    position: 0,
    audioPosition: 0,
    archivedAt: null,
    createdAt: now,
    updatedAt: now,
    deletedAt: null,
  });
  await storeParsedText(id, language, req.text);
  return { id };
}

/** Re-parse every text in a language (after parsing settings change). */
export async function reparseLanguage(langId: number): Promise<number> {
  const language = await localDb.languages.get(langId);
  if (!language) {
    return 0;
  }
  const texts = await localDb.texts
    .where('langId')
    .equals(langId)
    .and((t) => t.deletedAt == null)
    .toArray();
  for (const text of texts) {
    if (text.id != null) {
      await storeParsedText(text.id, language, text.text);
    }
  }
  return texts.length;
}

/** Load a text's words + reading config for the reader. */
export async function getTextWords(
  textId: number
): Promise<TextWordsResponse | { error: string }> {
  const text = await localDb.texts.get(textId);
  if (!text) {
    return { error: 'Text not found' };
  }
  const language = await localDb.languages.get(text.langId);
  if (!language) {
    return { error: 'Language not found' };
  }

  const occ = await localDb.occurrences
    .where('textId')
    .equals(textId)
    .sortBy('order');

  const ids = [
    ...new Set(occ.map((o) => o.woId).filter((x): x is number => x != null)),
  ];
  const wordsById = new Map<number, LocalWord>();
  for (const w of await localDb.words.bulkGet(ids)) {
    if (w && w.id != null) {
      wordsById.set(w.id, w);
    }
  }

  return {
    words: assembleTextWords(occ, wordsById),
    config: buildReadingConfig(text, language),
  };
}

/** Aggregate word-status statistics across one or more texts. */
export async function getStatistics(
  textIds: number[]
): Promise<TextStatistics> {
  let total = 0;
  let unknown = 0;
  let learning = 0;
  let learned = 0;
  let wellKnown = 0;
  let ignored = 0;
  const uniqueTerms = new Set<string>();
  const statusBreakdown: Record<number, number> = {};

  for (const textId of textIds) {
    const occ = await localDb.occurrences
      .where('textId')
      .equals(textId)
      .and((o) => o.isWord)
      .toArray();
    const ids = [
      ...new Set(occ.map((o) => o.woId).filter((x): x is number => x != null)),
    ];
    const wordsById = new Map<number, LocalWord>();
    for (const w of await localDb.words.bulkGet(ids)) {
      if (w && w.id != null) {
        wordsById.set(w.id, w);
      }
    }
    for (const o of occ) {
      total += 1;
      uniqueTerms.add(o.textLc);
      const word = o.woId != null ? wordsById.get(o.woId) : undefined;
      const status = word ? word.status : WordStatus.UNKNOWN;
      statusBreakdown[status] = (statusBreakdown[status] ?? 0) + 1;
      if (!word) {
        unknown += 1;
      } else if (status === WordStatus.WELL_KNOWN) {
        wellKnown += 1;
      } else if (status === WordStatus.IGNORED) {
        ignored += 1;
      } else if (status === WordStatus.LEARNED) {
        learned += 1;
      } else {
        learning += 1;
      }
    }
  }

  return {
    wordCounts: {
      total,
      unique: uniqueTerms.size,
      unknown,
      learning,
      learned,
      wellKnown,
      ignored,
    },
    statusBreakdown,
  };
}

/** Archive or delete a set of texts (soft-delete / tombstone). */
export async function bulkAction(
  action: 'archive' | 'delete',
  ids: number[]
): Promise<{ count: number }> {
  const now = nowMs();
  if (action === 'archive') {
    await localDb.texts.bulkUpdate(
      ids.map((id) => ({ key: id, changes: { archivedAt: now, updatedAt: now } }))
    );
  } else {
    await localDb.texts.bulkUpdate(
      ids.map((id) => ({ key: id, changes: { deletedAt: now, updatedAt: now } }))
    );
    for (const id of ids) {
      await localDb.occurrences.where('textId').equals(id).delete();
      await localDb.sentences.where('textId').equals(id).delete();
    }
  }
  return { count: ids.length };
}

/**
 * Mark every still-unknown word in a text with a terminal status (well-known or
 * ignored), creating the word rows and linking occurrences.
 */
async function markAll(textId: number, status: number): Promise<MarkAllResponse> {
  const text = await localDb.texts.get(textId);
  if (!text) {
    return { count: 0 };
  }
  const langId = text.langId;
  const occ = await localDb.occurrences
    .where('textId')
    .equals(textId)
    .and((o) => o.isWord && o.woId == null)
    .toArray();

  const seen = new Map<string, MarkedWordData>();
  for (const o of occ) {
    if (seen.has(o.textLc)) {
      continue;
    }
    const woId = (await localDb.words.add(
      buildWordRow(langId, o.text, status)
    )) as number;
    await localDb.occurrences
      .where('[langId+textLc]')
      .equals([langId, o.textLc])
      .and((x) => x.isWord)
      .modify({ woId });
    seen.set(o.textLc, {
      wid: woId,
      hex: toClassName(o.textLc),
      term: o.text,
      status,
    });
  }

  return { count: seen.size, words: [...seen.values()] };
}

/** Mark all unknown words well-known (status 99). */
export function markAllWellKnown(textId: number): Promise<MarkAllResponse> {
  return markAll(textId, WordStatus.WELL_KNOWN);
}

/** Mark all unknown words ignored (status 98). */
export function markAllIgnored(textId: number): Promise<MarkAllResponse> {
  return markAll(textId, WordStatus.IGNORED);
}
