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
import { seedIfNeeded, getCurrentLanguageId } from '@shared/offline/local/repositories';
import { apiGet } from '@shared/api/client';
import type { NavbarData } from '@shared/components/navbar_renderer';
import { LanguagesApi } from '@modules/language/api/languages_api';
import { TextsApi } from '@modules/text/api/texts_api';
import { TermsApi } from '@modules/vocabulary/api/terms_api';
import { setLangAsync } from '@modules/language/stores/language_settings';

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

  it('lists a language’s texts through TextsApi-style /texts/by-language', async () => {
    setLocalFirst(true);
    await seedIfNeeded();
    const text = await localDb.texts.toCollection().first();
    const langId = text!.langId;

    // The library page calls this endpoint directly via apiGet; without a local
    // route it falls through to the network and the list spins forever.
    const res = await apiGet<{
      texts: Array<{ id: number; title: string }>;
      pagination: { current_page: number; total: number; total_pages: number };
    }>(`/texts/by-language/${langId}`, { page: 1, per_page: 10, sort: 1 });

    expect(res.error).toBeUndefined();
    expect(res.data?.texts.length ?? 0).toBeGreaterThan(0);
    expect(res.data?.pagination.total ?? 0).toBeGreaterThan(0);
    expect(res.data?.pagination.current_page).toBe(1);
  });

  it('serves the global navbar chrome from the local DB', async () => {
    setLocalFirst(true);
    await seedIfNeeded();
    const res = await apiGet<NavbarData>('/navbar');
    expect(res.error).toBeUndefined();
    expect(res.data?.languages.length ?? 0).toBeGreaterThan(0);
    // Server-only menu sections stay hidden offline.
    expect(res.data?.isMultiUser).toBe(false);
    expect(res.data?.showAdminItems).toBe(false);
  });

  it('switches the current language offline via the navbar (setLangAsync)', async () => {
    setLocalFirst(true);
    await seedIfNeeded();
    const langs = await localDb.languages.toArray();
    const target = langs[langs.length - 1].id as number;
    await setLangAsync(String(target));
    expect(await getCurrentLanguageId()).toBe(target);
  });

  it('returns example sentences for a term through the local seam', async () => {
    setLocalFirst(true);
    await seedIfNeeded();
    const text = await localDb.texts.toCollection().first();
    const occ = await localDb.occurrences
      .where('textId')
      .equals(text!.id as number)
      .and((o) => o.isWord)
      .first();

    const res = await apiGet<[string, string][]>('/sentences-with-term', {
      language_id: text!.langId,
      term_lc: occ!.textLc
    });
    expect(res.error).toBeUndefined();
    expect(res.data?.length ?? 0).toBeGreaterThan(0);
    // [0] = display HTML (term in <b>), [1] = copy text (term in {…}).
    expect(res.data![0][0]).toContain('<b>');
    expect(res.data![0][1]).toContain('{');
  });
});
