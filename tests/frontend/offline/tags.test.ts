/**
 * Tests the offline tag layer: the `/tags*` list endpoints, word-tag
 * read-back (edit form + list), tag-based vocabulary filtering, and text tags.
 * Mirrors the PHP Tags module's behaviour with no server.
 */

import 'fake-indexeddb/auto';
import { describe, it, expect, beforeEach, afterAll } from 'vitest';
import { localDb } from '@shared/offline/local/schema';
import { setLocalFirst } from '@shared/offline/local/router';
import { createLanguage } from '@shared/offline/local/repositories/languages';
import { createText, getTextsByLanguage } from '@shared/offline/local/repositories/texts';
import { createFull, getForEdit } from '@shared/offline/local/repositories/terms';
import { getList, getFilterOptions } from '@shared/offline/local/repositories/words';
import {
  getAllTermTags,
  getAllTextTags,
} from '@shared/offline/local/repositories/tags';
import { apiGet } from '@shared/api/client';
import type { LanguageCreateRequest } from '@modules/language/api/languages_api';
import type { TextWordsResponse } from '@modules/text/api/texts_api';
import { getTextWords } from '@shared/offline/local/repositories/texts';

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
  setLocalFirst(false);
});

afterAll(() => {
  setLocalFirst(false);
});

function words(resp: TextWordsResponse | { error: string }): TextWordsResponse {
  if ('error' in resp) {
    throw new Error(resp.error);
  }
  return resp;
}

/** Save `word` at its first occurrence with the given tags; return its id. */
async function saveWordWithTags(
  textId: number,
  textLc: string,
  tags: string[],
  status = 1
): Promise<number> {
  const snapshot = words(await getTextWords(textId));
  const occ = snapshot.words.find((w) => w.textLc === textLc);
  if (!occ) {
    throw new Error(`occurrence not found: ${textLc}`);
  }
  const full = await createFull({
    textId,
    position: occ.position,
    translation: textLc,
    status,
    tags,
  });
  return full.term!.id;
}

async function setup(): Promise<{ langId: number; textId: number }> {
  const lang = await createLanguage(ENGLISH);
  const langId = lang.id as number;
  const text = await createText({
    langId,
    title: 'Sample',
    text: 'The cat sat. The dog ran. The bird flew.',
  });
  return { langId, textId: text.id as number };
}

describe('term tags', () => {
  it('persists tags and reads them back in the edit form', async () => {
    const { textId } = await setup();
    const id = await saveWordWithTags(textId, 'cat', ['animal', 'pet']);

    const snapshot = words(await getTextWords(textId));
    const occ = snapshot.words.find((w) => w.textLc === 'cat')!;
    const edit = await getForEdit(textId, occ.position, id);
    if ('error' in edit) {
      throw new Error(edit.error);
    }
    expect(edit.term.tags).toEqual(['animal', 'pet']);
    // All known tag names show up for autocomplete.
    expect(edit.allTags.sort()).toEqual(['animal', 'pet']);
  });

  it('replaces tags on update rather than appending', async () => {
    const { textId } = await setup();
    const id = await saveWordWithTags(textId, 'cat', ['animal', 'pet']);
    // Re-save with a different set.
    const snapshot = words(await getTextWords(textId));
    const occ = snapshot.words.find((w) => w.textLc === 'cat')!;
    await createFull({ textId, position: occ.position, translation: 'cat', status: 1, tags: ['wild'] });

    const edit = await getForEdit(textId, occ.position, id);
    if ('error' in edit) {
      throw new Error(edit.error);
    }
    expect(edit.term.tags).toEqual(['wild']);
  });

  it('lists all term tags via getAllTermTags (sorted, unique)', async () => {
    const { textId } = await setup();
    await saveWordWithTags(textId, 'cat', ['pet', 'animal']);
    await saveWordWithTags(textId, 'dog', ['animal']);
    expect(await getAllTermTags()).toEqual(['animal', 'pet']);
  });
});

describe('vocabulary list tag filter', () => {
  it('renders a word’s tags and filters by tag id, untagged, AND/OR', async () => {
    const { langId, textId } = await setup();
    await saveWordWithTags(textId, 'cat', ['animal', 'pet']);
    await saveWordWithTags(textId, 'dog', ['animal']);
    await saveWordWithTags(textId, 'bird', []); // untagged

    const opts = await getFilterOptions(langId);
    const animal = opts.tags.find((t) => t.name === 'animal')!.id;
    const pet = opts.tags.find((t) => t.name === 'pet')!.id;

    // Tags are rendered on each row.
    const all = await getList({ lang: langId });
    const catRow = all.words.find((w) => w.text === 'cat')!;
    expect(catRow.tags).toBe('animal, pet');

    // Single tag.
    const animals = await getList({ lang: langId, tag1: animal });
    expect(animals.words.map((w) => w.text).sort()).toEqual(['cat', 'dog']);

    // Untagged (-1).
    const untagged = await getList({ lang: langId, tag1: -1 });
    expect(untagged.words.map((w) => w.text)).toEqual(['bird']);

    // AND: animal AND pet -> cat only.
    const both = await getList({ lang: langId, tag1: animal, tag2: pet, tag12: 1 });
    expect(both.words.map((w) => w.text)).toEqual(['cat']);

    // OR: animal OR pet -> cat + dog.
    const either = await getList({ lang: langId, tag1: animal, tag2: pet, tag12: 0 });
    expect(either.words.map((w) => w.text).sort()).toEqual(['cat', 'dog']);
  });
});

describe('text tags', () => {
  it('stores text tags on create and surfaces them in the library list', async () => {
    const lang = await createLanguage(ENGLISH);
    const langId = lang.id as number;
    await createText({
      langId,
      title: 'Tagged',
      text: 'Hello world.',
      tags: ['news', 'beginner'],
    });

    expect(await getAllTextTags()).toEqual(['beginner', 'news']);

    const list = await getTextsByLanguage(langId, 1, 10, 1);
    expect(list.texts[0].taglist).toContain('news');
    expect(list.texts[0].taglist).toContain('beginner');
  });
});

describe('tag endpoints through the local seam', () => {
  it('serves /tags, /tags/term and /tags/text from the on-device DB', async () => {
    const { textId } = await setup();
    await saveWordWithTags(textId, 'cat', ['animal']);
    const langId = (await localDb.languages.toCollection().first())!.id as number;
    await createText({ langId, title: 'T', text: 'Hi.', tags: ['news'] });

    setLocalFirst(true);

    const term = await apiGet<string[]>('/tags/term');
    expect(term.error).toBeUndefined();
    expect(term.data).toEqual(['animal']);

    const text = await apiGet<string[]>('/tags/text');
    expect(text.data).toEqual(['news']);

    const both = await apiGet<{ term: string[]; text: string[] }>('/tags');
    expect(both.data).toEqual({ term: ['animal'], text: ['news'] });
  });
});
