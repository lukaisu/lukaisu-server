/**
 * Integration tests for the local-first repositories against a real Dexie
 * instance (fake-indexeddb). Exercises the milestone path end-to-end:
 * create a language → import a text → read with status highlighting →
 * save words → review them — all with no server.
 */

import 'fake-indexeddb/auto';
import { describe, it, expect, beforeEach } from 'vitest';
import { localDb } from '@shared/offline/local/schema';
import { createLanguage } from '@shared/offline/local/repositories/languages';
import {
  createText,
  getTextWords,
  getStatistics,
  markAllWellKnown,
} from '@shared/offline/local/repositories/texts';
import {
  createQuick,
  createFull,
  setStatus,
} from '@shared/offline/local/repositories/terms';
import {
  getReviewConfig,
  getNextWord,
  updateStatus,
  getTomorrowCount,
} from '@shared/offline/local/repositories/review';
import { seedIfNeeded } from '@shared/offline/local/repositories/seed';
import type { TextWordsResponse } from '@modules/text/api/texts_api';
import type { LanguageCreateRequest } from '@modules/language/api/languages_api';

const ENGLISH: LanguageCreateRequest = {
  name: 'English',
  sourceLang: 'en',
  dict1Uri: 'https://en.wiktionary.org/wiki/lukaisu_term',
  translatorUri: '',
  regexpSplitSentences: '.!?:;',
  exceptionsSplitSentences: 'Mr.|Mrs.|Dr.|[A-Z].',
  regexpWordCharacters: 'a-zA-ZÀ-ÖØ-öø-ȳ',
};

beforeEach(async () => {
  await Promise.all(localDb.tables.map((t) => t.clear()));
});

async function setupEnglishText(
  text = 'The cat sat. The cat ran.'
): Promise<{ langId: number; textId: number }> {
  const lang = await createLanguage(ENGLISH);
  const langId = lang.id as number;
  const created = await createText({ langId, title: 'Sample', text });
  return { langId, textId: created.id as number };
}

function words(resp: TextWordsResponse | { error: string }): TextWordsResponse {
  if ('error' in resp) {
    throw new Error(resp.error);
  }
  return resp;
}

describe('reading path', () => {
  it('imports a text and renders all words as unknown initially', async () => {
    const { textId } = await setupEnglishText();
    const resp = words(await getTextWords(textId));

    expect(resp.config.title).toBe('Sample');
    const wordTokens = resp.words.filter((w) => !w.isNotWord);
    expect(wordTokens.length).toBeGreaterThan(0);
    expect(wordTokens.every((w) => w.status === 0)).toBe(true);
    // Two sentences, so at least two distinct sentence ids.
    expect(new Set(resp.words.map((w) => w.sentenceId)).size).toBe(2);
  });

  it('reflects a saved word across every occurrence in the text', async () => {
    const { textId } = await setupEnglishText();

    // Find the position of the first "cat" occurrence.
    const before = words(await getTextWords(textId));
    const cat = before.words.find((w) => w.textLc === 'cat');
    expect(cat).toBeDefined();

    const quick = await createQuick(textId, cat!.position, 99);
    expect(quick.term_id).toBeGreaterThan(0);

    const after = words(await getTextWords(textId));
    const catOccurrences = after.words.filter((w) => w.textLc === 'cat');
    // "cat" appears twice; both should now show status 99.
    expect(catOccurrences.length).toBe(2);
    expect(catOccurrences.every((w) => w.status === 99)).toBe(true);
    expect(catOccurrences.every((w) => w.wordId === quick.term_id)).toBe(true);
  });

  it('computes status statistics for a text', async () => {
    const { textId } = await setupEnglishText();
    const before = words(await getTextWords(textId));
    const cat = before.words.find((w) => w.textLc === 'cat')!;
    await createQuick(textId, cat.position, 99);

    const stats = await getStatistics([textId]);
    expect(stats.wordCounts.total).toBeGreaterThan(0);
    expect(stats.wordCounts.wellKnown).toBe(2); // both "cat" occurrences
    expect(stats.wordCounts.unknown).toBeGreaterThan(0);
  });

  it('marks all unknown words well-known', async () => {
    const { textId } = await setupEnglishText();
    const result = await markAllWellKnown(textId);
    expect(result.count).toBeGreaterThan(0);

    const after = words(await getTextWords(textId));
    expect(after.words.filter((w) => !w.isNotWord).every((w) => w.status === 99)).toBe(true);
  });
});

describe('review path', () => {
  it('schedules a learned word and advances it on a correct answer', async () => {
    const { langId, textId } = await setupEnglishText();
    const snapshot = words(await getTextWords(textId));
    const cat = snapshot.words.find((w) => w.textLc === 'cat')!;

    // Save "cat" as a status-1 word with a translation.
    const full = await createFull({
      textId,
      position: cat.position,
      translation: 'feline',
      status: 1,
    });
    const termId = full.term!.id;

    // Backdate the status change so the word is due today.
    await localDb.words.update(termId, {
      statusChanged: Date.now() - 3 * 86_400_000,
    });

    const config = await getReviewConfig({ lang: langId });
    if ('error' in config) {
      throw new Error(config.error);
    }
    expect(config.progress.total).toBe(1);

    const next = await getNextWord({
      reviewKey: config.reviewKey,
      selection: config.selection,
      wordMode: true,
      lgId: langId,
      wordRegex: config.wordRegex,
      type: 0,
    });
    expect(next.term_id).toBe(termId);
    expect(next.solution).toBe('feline');

    // Answer correctly: status advances 1 -> 2 and it is no longer due.
    const updated = await updateStatus(termId, undefined, 1);
    expect(updated.status).toBe(2);

    const afterConfig = await getReviewConfig({ lang: langId });
    if ('error' in afterConfig) {
      throw new Error(afterConfig.error);
    }
    expect(afterConfig.progress.total).toBe(0);
  });

  it('does not surface words without a usable translation', async () => {
    const { langId, textId } = await setupEnglishText();
    const snapshot = words(await getTextWords(textId));
    const cat = snapshot.words.find((w) => w.textLc === 'cat')!;
    // Quick-create leaves translation empty; set a learning status + backdate.
    await setStatus((await createQuick(textId, cat.position, 99)).term_id!, 1);
    const w = await localDb.words.where('textLc').equals('cat').first();
    await localDb.words.update(w!.id!, { statusChanged: Date.now() - 5 * 86_400_000 });

    const tomorrow = await getTomorrowCount('local', `lang:${langId}`);
    expect(tomorrow.count).toBe(0); // no translation -> never reviewable
  });
});

describe('seeding', () => {
  it('seeds languages and sample texts on first run only', async () => {
    const first = await seedIfNeeded();
    expect(first).toBe(true);
    const langCount = await localDb.languages.count();
    expect(langCount).toBeGreaterThan(0);
    const textCount = await localDb.texts.count();
    expect(textCount).toBeGreaterThan(0);

    // A seeded text actually parsed into readable words.
    const someText = await localDb.texts.toCollection().first();
    const resp = words(await getTextWords(someText!.id!));
    expect(resp.words.some((w) => !w.isNotWord)).toBe(true);

    // Idempotent.
    const second = await seedIfNeeded();
    expect(second).toBe(false);
  });
});
