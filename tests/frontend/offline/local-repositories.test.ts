/**
 * Integration tests for the local-first repositories against a real Dexie
 * instance (fake-indexeddb). Exercises the milestone path end-to-end:
 * create a language → import a text → read with status highlighting →
 * save words → review them — all with no server.
 */

import 'fake-indexeddb/auto';
import { describe, it, expect, beforeEach } from 'vitest';
import { localDb } from '@shared/offline/local/schema';
import {
  createLanguage,
  listLanguagesWithArchivedTexts,
} from '@shared/offline/local/repositories/languages';
import {
  createText,
  getText,
  updateText,
  getTextWords,
  getStatistics,
  markAllWellKnown,
  archiveText,
  unarchiveText,
  deleteText,
  getTextsByLanguage,
  getArchivedTextsByLanguage,
} from '@shared/offline/local/repositories/texts';
import {
  createQuick,
  createFull,
  updateFull,
  deleteTerm,
  getTerm,
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

    // Per-text map keyed by text id (the shape the library list consumes).
    const stats = await getStatistics([textId]);
    const textStats = stats[String(textId)];
    expect(textStats.total).toBeGreaterThan(0);
    expect(textStats.statusCounts['99']).toBe(2); // both "cat" occurrences
    expect(textStats.unknown).toBeGreaterThan(0);
    expect(textStats.saved).toBe(textStats.total - textStats.unknown);
    expect(textStats.unknownPercent).toBe(
      Math.round((textStats.unknown / textStats.total) * 100)
    );
  });

  it('marks all unknown words well-known', async () => {
    const { textId } = await setupEnglishText();
    const result = await markAllWellKnown(textId);
    expect(result.count).toBeGreaterThan(0);

    const after = words(await getTextWords(textId));
    expect(after.words.filter((w) => !w.isNotWord).every((w) => w.status === 99)).toBe(true);
  });
});

describe('text management (archive / unarchive / delete)', () => {
  it('archives a text out of the active list and into the archived list', async () => {
    const { langId, textId } = await setupEnglishText();

    // Starts active.
    expect((await getTextsByLanguage(langId, 1, 10, 1)).pagination.total).toBe(1);
    expect((await getArchivedTextsByLanguage(langId, 1, 10, 1)).pagination.total).toBe(0);

    const res = await archiveText(textId);
    expect('archived' in res && res.archived).toBe(true);

    // Now archived: gone from active, present in archived.
    expect((await getTextsByLanguage(langId, 1, 10, 1)).pagination.total).toBe(0);
    expect((await getArchivedTextsByLanguage(langId, 1, 10, 1)).pagination.total).toBe(1);
  });

  it('unarchives a text back into the active list', async () => {
    const { langId, textId } = await setupEnglishText();
    await archiveText(textId);

    const res = await unarchiveText(textId);
    expect('unarchived' in res && res.unarchived).toBe(true);

    expect((await getTextsByLanguage(langId, 1, 10, 1)).pagination.total).toBe(1);
    expect((await getArchivedTextsByLanguage(langId, 1, 10, 1)).pagination.total).toBe(0);
  });

  it('deletes a text and drops its parsed structures', async () => {
    const { langId, textId } = await setupEnglishText();
    expect((await localDb.occurrences.where('textId').equals(textId).count())).toBeGreaterThan(0);

    const res = await deleteText(textId);
    expect('deleted' in res && res.deleted).toBe(true);

    expect((await getTextsByLanguage(langId, 1, 10, 1)).pagination.total).toBe(0);
    expect(await localDb.occurrences.where('textId').equals(textId).count()).toBe(0);
    expect(await localDb.sentences.where('textId').equals(textId).count()).toBe(0);
  });

  it('errors when archiving / deleting a missing text', async () => {
    const archived = await archiveText(999999);
    expect('error' in archived).toBe(true);
    const deleted = await deleteText(999999);
    expect('error' in deleted).toBe(true);
  });

  it('reports only languages that have archived texts, with their counts', async () => {
    const { langId, textId } = await setupEnglishText();

    // No archived texts yet -> the language is absent from the grouped view.
    expect((await listLanguagesWithArchivedTexts()).languages).toHaveLength(0);

    await archiveText(textId);
    const withArchived = await listLanguagesWithArchivedTexts();
    expect(withArchived.languages).toHaveLength(1);
    expect(withArchived.languages[0].id).toBe(langId);
    expect(withArchived.languages[0].text_count).toBe(1);
  });
});

describe('single text edit (text-edit.html)', () => {
  it('loads a text\'s editable fields, with tags and archived flag', async () => {
    const { textId } = await setupEnglishText();
    await archiveText(textId);

    const rec = await getText(textId);
    if ('error' in rec) throw new Error(rec.error);
    expect(rec.title).toBe('Sample');
    expect(rec.text).toBe('The cat sat. The cat ran.');
    expect(rec.archived).toBe(true);
    expect(rec.tags).toEqual([]);
  });

  it('errors when loading a missing text', async () => {
    expect('error' in (await getText(999999))).toBe(true);
  });

  it('updates fields without re-parsing when the body is unchanged', async () => {
    const { langId, textId } = await setupEnglishText();
    const occBefore = await localDb.occurrences.where('textId').equals(textId).toArray();

    const res = await updateText(textId, {
      title: 'Renamed',
      langId,
      text: 'The cat sat. The cat ran.',
      tags: ['news'],
    });
    expect(res.updated).toBe(true);
    expect(res.reparsed).toBe(false);

    const rec = await getText(textId);
    if ('error' in rec) throw new Error(rec.error);
    expect(rec.title).toBe('Renamed');
    expect(rec.tags).toEqual(['news']);
    // Occurrence rows are untouched (ids stable) when the body didn't change.
    const occAfter = await localDb.occurrences.where('textId').equals(textId).toArray();
    expect(occAfter.map((o) => o.id)).toEqual(occBefore.map((o) => o.id));
  });

  it('re-parses (rebuilds sentences + occurrences) when the body changes', async () => {
    const { langId, textId } = await setupEnglishText();
    const sentBefore = await localDb.sentences.where('textId').equals(textId).count();

    const res = await updateText(textId, {
      title: 'Sample',
      langId,
      text: 'A dog barks. A dog runs. A dog sleeps.',
    });
    expect(res.updated).toBe(true);
    expect(res.reparsed).toBe(true);

    // The reader now reflects the new body.
    const rendered = words(await getTextWords(textId));
    expect(rendered.words.some((w) => w.text === 'dog')).toBe(true);
    expect(rendered.words.some((w) => w.text === 'cat')).toBe(false);
    const sentAfter = await localDb.sentences.where('textId').equals(textId).count();
    expect(sentAfter).toBeGreaterThan(sentBefore);
  });

  it('clears tags when an empty tag list is saved', async () => {
    const { langId, textId } = await setupEnglishText();
    const body = 'The cat sat. The cat ran.';
    await updateText(textId, { title: 'Sample', langId, text: body, tags: ['keep'] });
    await updateText(textId, { title: 'Sample', langId, text: body, tags: [] });
    const rec = await getText(textId);
    if ('error' in rec) throw new Error(rec.error);
    expect(rec.tags).toEqual([]);
  });

  it('errors when updating a missing text', async () => {
    const res = await updateText(999999, { title: 'x', langId: 1, text: 'y' });
    expect(res.updated).toBe(false);
    expect(res.error).toBeTruthy();
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

describe('standalone term edit (word.html)', () => {
  it('loads a term by id, round-trips a full edit, and deletes it', async () => {
    const { textId } = await setupEnglishText();
    const snapshot = words(await getTextWords(textId));
    const cat = snapshot.words.find((w) => w.textLc === 'cat')!;

    const full = await createFull({
      textId,
      position: cat.position,
      translation: 'feline',
      romanization: 'kat',
      sentence: 'The {cat} sat.',
      notes: 'a note',
      status: 1,
      tags: ['animal'],
    });
    const termId = full.term!.id;

    // GET /terms/{id} returns the full editable shape — incl. notes + tags, which
    // the form prefills (the server's GET omits them; offline we have them).
    const loaded = await getTerm(termId);
    if ('error' in loaded) {
      throw new Error(loaded.error);
    }
    expect(loaded.text).toBe('cat');
    expect(loaded.translation).toBe('feline');
    expect(loaded.romanization).toBe('kat');
    expect(loaded.notes).toBe('a note');
    expect(loaded.status).toBe(1);
    expect(loaded.tags).toEqual(['animal']);

    // The form's save (updateFull) changes every field; reload reflects it.
    await updateFull(termId, {
      translation: 'a cat',
      romanization: 'neko',
      sentence: 'The {cat} ran.',
      notes: 'updated',
      lemma: 'cat',
      status: 3,
      tags: ['animal', 'noun'],
    });
    const after = await getTerm(termId);
    if ('error' in after) {
      throw new Error(after.error);
    }
    expect(after.translation).toBe('a cat');
    expect(after.status).toBe(3);
    expect(after.lemma).toBe('cat');
    expect((after.tags ?? []).sort()).toEqual(['animal', 'noun']);

    // The form's delete removes it; a subsequent load errors.
    expect(await deleteTerm(termId)).toEqual({ deleted: true });
    expect(await getTerm(termId)).toEqual({ error: 'Term not found' });
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
