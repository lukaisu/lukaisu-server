/**
 * Tests for the server-enhanced lemmatization client. fetch is mocked, so these
 * assert the contract: Korean -> Kiwi, the request shape sent to the server, and
 * that every failure mode degrades to null (the save path must never break).
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { remoteLemmatize, lemmatizerFor } from '@shared/offline/nlp/lemmatize';

describe('lemmatizerFor', () => {
  it('routes Korean (incl. regioned) to Kiwi', () => {
    expect(lemmatizerFor('ko')).toBe('kiwi');
    expect(lemmatizerFor('ko-KR')).toBe('kiwi');
    expect(lemmatizerFor('KO')).toBe('kiwi');
  });
  it('routes other languages to spaCy', () => {
    expect(lemmatizerFor('en')).toBe('spacy');
    expect(lemmatizerFor('ja')).toBe('spacy');
    expect(lemmatizerFor('zh')).toBe('spacy');
  });
});

describe('remoteLemmatize', () => {
  const mockFetch = vi.fn();
  const originalFetch = global.fetch;

  beforeEach(() => {
    mockFetch.mockReset();
    global.fetch = mockFetch;
  });
  afterEach(() => {
    global.fetch = originalFetch;
  });

  function ok(body: unknown) {
    return { ok: true, json: () => Promise.resolve(body) };
  }

  it('sends word/language/lemmatizer to /api/v1/lemmatize and returns the lemma', async () => {
    mockFetch.mockResolvedValue(ok({ word: '공부했습니다', lemma: '공부하다' }));

    const lemma = await remoteLemmatize('공부했습니다', 'ko');

    expect(lemma).toBe('공부하다');
    expect(mockFetch).toHaveBeenCalledTimes(1);
    const [calledUrl, init] = mockFetch.mock.calls[0];
    expect(String(calledUrl)).toMatch(/\/api\/v1\/lemmatize$/);
    expect(init.method).toBe('POST');
    expect(JSON.parse(init.body)).toEqual({
      word: '공부했습니다',
      language: 'ko',
      lemmatizer: 'kiwi',
    });
  });

  it('accepts a PHP-style wrapped { data: { lemma } } payload', async () => {
    mockFetch.mockResolvedValue(ok({ data: { lemma: '가다' } }));
    expect(await remoteLemmatize('갔습니다', 'ko')).toBe('가다');
  });

  it('returns null when the lemma equals the input (already base form)', async () => {
    mockFetch.mockResolvedValue(ok({ lemma: '학교' }));
    expect(await remoteLemmatize('학교', 'ko')).toBeNull();
  });

  it('returns null on a non-OK response', async () => {
    mockFetch.mockResolvedValue({ ok: false, json: () => Promise.resolve({}) });
    expect(await remoteLemmatize('갔습니다', 'ko')).toBeNull();
  });

  it('returns null (never throws) when the request rejects — offline', async () => {
    mockFetch.mockRejectedValue(new Error('network down'));
    await expect(remoteLemmatize('갔습니다', 'ko')).resolves.toBeNull();
  });

  it('does not call the server for empty/whitespace input', async () => {
    expect(await remoteLemmatize('   ', 'ko')).toBeNull();
    expect(mockFetch).not.toHaveBeenCalled();
  });

  it('uses the spaCy backend for non-Korean languages', async () => {
    mockFetch.mockResolvedValue(ok({ lemma: 'run' }));
    await remoteLemmatize('running', 'en');
    expect(JSON.parse(mockFetch.mock.calls[0][1].body).lemmatizer).toBe('spacy');
  });
});
