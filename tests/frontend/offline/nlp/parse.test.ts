/**
 * Tests for the server-enhanced tokenization client. `adaptParse` is the
 * delicate part — it reassembles per-token sentence indices from the server's
 * flat token stream — so it gets focused coverage, including the bail-out cases
 * that protect the reader from a misaligned parse. fetch is mocked for
 * `remoteParse` to assert the request shape and the soft-fail contract.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { adaptParse, remoteParse, serverParserFor } from '@shared/offline/nlp/parse';

describe('serverParserFor', () => {
  it('routes Korean to Kiwi and nothing else (for now)', () => {
    expect(serverParserFor('ko')).toBe('kiwi');
    expect(serverParserFor('ko-KR')).toBe('kiwi');
    expect(serverParserFor('ja')).toBeNull();
    expect(serverParserFor('en')).toBeNull();
  });
});

describe('adaptParse', () => {
  it('assigns per-token sentence indices by reconstructing each sentence', () => {
    const result = adaptParse({
      sentences: ['저는 학생.'],
      tokens: [
        { text: '저', is_word: true },
        { text: '는', is_word: true },
        { text: ' ', is_word: false },
        { text: '학생', is_word: true },
        { text: '.', is_word: false },
      ],
    });

    expect(result).not.toBeNull();
    expect(result!.sentences).toEqual(['저는 학생.']);
    expect(result!.tokens.map((t) => [t.text, t.sentenceIndex, t.order, t.isWord])).toEqual([
      ['저', 0, 0, true],
      ['는', 0, 1, true],
      [' ', 0, 2, false],
      ['학생', 0, 3, true],
      ['.', 0, 4, false],
    ]);
    // Reconstructs the input, and word tokens carry the morpheme split.
    expect(result!.tokens.map((t) => t.text).join('')).toBe('저는 학생.');
  });

  it('splits tokens across multiple sentences', () => {
    const result = adaptParse({
      sentences: ['가다.', '오다.'],
      tokens: [
        { text: '가다', is_word: true },
        { text: '.', is_word: false },
        { text: '오다', is_word: true },
        { text: '.', is_word: false },
      ],
    });
    expect(result!.tokens.map((t) => t.sentenceIndex)).toEqual([0, 0, 1, 1]);
    expect(result!.tokens.filter((t) => t.sentenceIndex === 1).map((t) => t.order)).toEqual([0, 1]);
  });

  it('bails (null) when tokens do not reconstruct the sentence', () => {
    expect(
      adaptParse({
        sentences: ['저는 학생.'],
        tokens: [{ text: '저', is_word: true }, { text: '학생', is_word: true }],
      })
    ).toBeNull();
  });

  it('bails when tokens are left over after the last sentence', () => {
    expect(
      adaptParse({
        sentences: ['가.'],
        tokens: [
          { text: '가', is_word: true },
          { text: '.', is_word: false },
          { text: '나', is_word: true },
        ],
      })
    ).toBeNull();
  });

  it('returns null for an empty token list', () => {
    expect(adaptParse({ sentences: [''], tokens: [] })).toBeNull();
  });
});

describe('remoteParse', () => {
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

  it('POSTs {text, parser} to /api/v1/parse and adapts the result', async () => {
    mockFetch.mockResolvedValue(
      ok({
        sentences: ['공부하다.'],
        tokens: [
          { text: '공부하다', is_word: true },
          { text: '.', is_word: false },
        ],
      })
    );

    const result = await remoteParse('공부하다.', 'kiwi');

    expect(result).not.toBeNull();
    expect(result!.tokens[0]).toMatchObject({ text: '공부하다', isWord: true, sentenceIndex: 0 });
    const [calledUrl, init] = mockFetch.mock.calls[0];
    expect(String(calledUrl)).toMatch(/\/api\/v1\/parse$/);
    expect(JSON.parse(init.body)).toEqual({ text: '공부하다.', parser: 'kiwi' });
  });

  it('accepts a PHP-style wrapped { data } payload', async () => {
    mockFetch.mockResolvedValue(
      ok({ data: { sentences: ['가.'], tokens: [{ text: '가', is_word: true }, { text: '.', is_word: false }] } })
    );
    const result = await remoteParse('가.', 'kiwi');
    expect(result!.tokens.map((t) => t.text)).toEqual(['가', '.']);
  });

  it('returns null on non-OK, rejection, or empty text (soft fail)', async () => {
    mockFetch.mockResolvedValue({ ok: false, json: () => Promise.resolve({}) });
    expect(await remoteParse('가.', 'kiwi')).toBeNull();

    mockFetch.mockRejectedValue(new Error('offline'));
    await expect(remoteParse('가.', 'kiwi')).resolves.toBeNull();

    mockFetch.mockClear();
    expect(await remoteParse('   ', 'kiwi')).toBeNull();
    expect(mockFetch).not.toHaveBeenCalled();
  });
});
