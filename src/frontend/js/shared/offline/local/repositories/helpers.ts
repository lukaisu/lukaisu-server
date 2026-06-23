/**
 * Shared helpers for the local repositories: word construction, score
 * maintenance, and occurrence (un)linking.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { localDb, type LocalLanguage, type LocalWord } from '../schema';
import { computeScoreFields } from '../review-scoring';
import type { ParserConfig } from '../parser';

/** Map a stored language to the tokenizer configuration. */
export function languageToParserConfig(language: LocalLanguage): ParserConfig {
  return {
    languageId: language.id ?? 0,
    regexpSplitSentences: language.regexpSplitSentences,
    exceptionsSplitSentences: language.exceptionsSplitSentences,
    regexpWordCharacters: language.regexpWordCharacters,
    characterSubstitutions: language.characterSubstitutions,
    removeSpaces: language.removeSpaces,
    splitEachChar: language.splitEachChar,
    rightToLeft: language.rightToLeft,
  };
}

/** Current time as epoch ms (single call site eases testing/mocking). */
export function nowMs(): number {
  return Date.now();
}

/** Fields a caller may supply when creating/updating a word. */
export interface WordFields {
  translation?: string;
  romanization?: string;
  sentence?: string;
  notes?: string;
  lemma?: string;
  wordCount?: number;
}

/**
 * Build a `LocalWord` row for insertion, computing the review scores from the
 * status and the current time.
 */
export function buildWordRow(
  langId: number,
  text: string,
  status: number,
  fields: WordFields = {}
): LocalWord {
  const now = nowMs();
  const lemma = fields.lemma ?? '';
  const scores = computeScoreFields(status, new Date(now));
  return {
    langId,
    text,
    textLc: text.toLowerCase(),
    lemma,
    lemmaLc: lemma.toLowerCase(),
    status,
    translation: fields.translation ?? '',
    romanization: fields.romanization ?? '',
    sentence: fields.sentence ?? '',
    notes: fields.notes ?? '',
    wordCount: fields.wordCount ?? 0,
    created: now,
    statusChanged: now,
    todayScore: scores.todayScore,
    tomorrowScore: scores.tomorrowScore,
    random: scores.random,
    updatedAt: now,
    deletedAt: null,
  };
}

/**
 * Point every matching occurrence (same language + lower-cased term) at a word
 * so the reader reflects its status. Only word tokens are linked.
 */
export async function linkWordOccurrences(
  woId: number,
  langId: number,
  textLc: string
): Promise<void> {
  await localDb.occurrences
    .where('[langId+textLc]')
    .equals([langId, textLc])
    .and((o) => o.isWord)
    .modify({ woId });
}

/** Detach a word from all occurrences (used on delete). */
export async function unlinkWordOccurrences(woId: number): Promise<void> {
  await localDb.occurrences.where('woId').equals(woId).modify({ woId: null });
}

/**
 * Update a word's status and refresh its review scores + change timestamp.
 * Returns the new status, or null if the word is gone.
 */
export async function applyStatus(
  woId: number,
  newStatus: number
): Promise<number | null> {
  const word = await localDb.words.get(woId);
  if (!word) {
    return null;
  }
  const now = nowMs();
  const scores = computeScoreFields(newStatus, new Date(now));
  await localDb.words.update(woId, {
    status: newStatus,
    statusChanged: now,
    todayScore: scores.todayScore,
    tomorrowScore: scores.tomorrowScore,
    random: scores.random,
    updatedAt: now,
  });
  return newStatus;
}

/** Look up an active word by language + lower-cased term, if any. */
export async function findWord(
  langId: number,
  textLc: string
): Promise<LocalWord | undefined> {
  const word = await localDb.words.where('[textLc+langId]').equals([textLc, langId]).first();
  return word && word.deletedAt == null ? word : undefined;
}
