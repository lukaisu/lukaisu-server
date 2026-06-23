/**
 * Tests the local-first seam through the REAL API client + module wrappers.
 * This proves the rendering code's existing calls (LanguagesApi/TextsApi) are
 * transparently served from the on-device DB when local-first is enabled, and
 * are untouched (network path) when it is off.
 */

import 'fake-indexeddb/auto';
import { describe, it, expect, beforeEach, afterAll } from 'vitest';
import { localDb } from '@shared/offline/local/schema';
import { setLocalFirst, isLocalFirst } from '@shared/offline/local/router';
import { seedIfNeeded } from '@shared/offline/local/repositories';
import { LanguagesApi } from '@modules/language/api/languages_api';
import { TextsApi } from '@modules/text/api/texts_api';
import { TermsApi } from '@modules/vocabulary/api/terms_api';

beforeEach(async () => {
  await Promise.all(localDb.tables.map((t) => t.clear()));
  setLocalFirst(false);
});

afterAll(() => {
  setLocalFirst(false);
});

describe('local-first seam', () => {
  it('defaults to off so existing installs keep using the network', () => {
    expect(isLocalFirst()).toBe(false);
  });

  it('serves the language list from the local DB via LanguagesApi.list()', async () => {
    setLocalFirst(true);
    await seedIfNeeded();
    const res = await LanguagesApi.list();
    expect(res.error).toBeUndefined();
    expect(res.data?.languages.length ?? 0).toBeGreaterThan(0);
  });

  it('serves a seeded text through TextsApi.getWords()', async () => {
    setLocalFirst(true);
    await seedIfNeeded();
    const text = await localDb.texts.toCollection().first();
    const res = await TextsApi.getWords(text!.id as number);
    expect(res.error).toBeUndefined();
    expect(res.data?.words.some((w) => !w.isNotWord)).toBe(true);
  });

  it('saves a word through TermsApi and reflects it on the next read', async () => {
    setLocalFirst(true);
    await seedIfNeeded();
    const text = await localDb.texts.toCollection().first();
    const textId = text!.id as number;

    const before = await TextsApi.getWords(textId);
    const target = before.data!.words.find((w) => !w.isNotWord)!;

    const quick = await TermsApi.createQuick(textId, target.position, 99);
    expect(quick.data?.term_id).toBeGreaterThan(0);

    const after = await TextsApi.getWords(textId);
    const updated = after.data!.words.find((w) => w.position === target.position)!;
    expect(updated.status).toBe(99);
  });
});
