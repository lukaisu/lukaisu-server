/**
 * Review repository — offline spaced-repetition. Picks due words using the
 * live score (recomputed from status + elapsed days, like the server's SQL) and
 * applies answers. `selection` is an internal token produced by
 * `getReviewConfig` and consumed by the other methods.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { localDb, type LocalLanguage, type LocalWord } from '../schema';
import {
  calculateScore,
  calculateTomorrowScore,
  dayDiff,
  hasUsableTranslation,
  isLearningStatus,
  nextStatus,
} from '../review-scoring';
import { applyStatus } from './helpers';
import { getCurrentLanguageId } from './settings';
import type {
  WordTestData,
  TomorrowCountResponse,
  ReviewStatusResponse,
  ReviewConfigResponse,
  ReviewLangSettings,
  TableWordsResponse,
  TableReviewWord,
  NextWordParams,
} from '@modules/review/api/review_api';

/** Resolve the candidate words for a `selection` token (`lang:<id>` / `text:<id>`). */
async function candidateWords(selection: string): Promise<LocalWord[]> {
  const [kind, rawId] = selection.split(':');
  const id = parseInt(rawId ?? '', 10);

  if (kind === 'text' && !Number.isNaN(id)) {
    const occ = await localDb.occurrences.where('textId').equals(id).and((o) => o.woId != null).toArray();
    const ids = [...new Set(occ.map((o) => o.woId).filter((x): x is number => x != null))];
    const words: LocalWord[] = [];
    for (const w of await localDb.words.bulkGet(ids)) {
      if (w && w.deletedAt == null) {
        words.push(w);
      }
    }
    return words;
  }

  if (kind === 'lang' && !Number.isNaN(id)) {
    return localDb.words.where('langId').equals(id).and((w) => w.deletedAt == null).toArray();
  }

  return localDb.words.filter((w) => w.deletedAt == null).toArray();
}

/** Words currently reviewable (learning status + usable translation). */
function reviewable(words: LocalWord[]): LocalWord[] {
  return words.filter(
    (w) => isLearningStatus(w.status) && hasUsableTranslation(w.translation)
  );
}

/** Words due now, sorted by urgency (lowest score first, then shuffle key). */
function dueWords(words: LocalWord[], now: Date): { word: LocalWord; score: number }[] {
  return reviewable(words)
    .map((w) => ({ word: w, score: calculateScore(w.status, dayDiff(now, new Date(w.statusChanged))) }))
    .filter((x) => x.score < 0)
    .sort((a, b) => a.score - b.score || a.word.random - b.word.random);
}

function langSettings(l: LocalLanguage): ReviewLangSettings {
  return {
    name: l.name,
    dict1Uri: l.dict1Uri,
    dict2Uri: l.dict2Uri,
    translateUri: l.translatorUri,
    textSize: l.textSize,
    rtl: l.rightToLeft,
    langCode: l.code,
  };
}

/** Build a review session configuration for a language or text. */
export async function getReviewConfig(params: {
  lang?: number;
  text?: number;
  selection?: number;
}): Promise<ReviewConfigResponse | { error: string }> {
  let selection: string;
  let langId: number;

  if (params.text) {
    const text = await localDb.texts.get(params.text);
    if (!text) {
      return { error: 'Text not found' };
    }
    selection = `text:${params.text}`;
    langId = text.langId;
  } else {
    langId = params.lang || (await getCurrentLanguageId());
    selection = `lang:${langId}`;
  }

  const language = await localDb.languages.get(langId);
  if (!language) {
    return { error: 'Language not found' };
  }

  const due = dueWords(await candidateWords(selection), new Date());
  const now = Date.now();
  return {
    reviewKey: 'local',
    selection,
    reviewType: 0,
    isTableMode: false,
    wordMode: true,
    langId,
    wordRegex: language.regexpWordCharacters,
    langSettings: langSettings(language),
    progress: { total: due.length, remaining: due.length, wrong: 0, correct: 0 },
    timer: { startTime: now, serverTime: now },
    title: language.name,
    property: '',
  };
}

/** The next word to review, or an empty record when the session is done. */
export async function getNextWord(params: NextWordParams): Promise<WordTestData> {
  const due = dueWords(await candidateWords(params.selection), new Date());
  const top = due[0];
  if (!top) {
    return { term_id: '', term_text: '', solution: '', group: '' };
  }
  return {
    term_id: top.word.id ?? 0,
    term_text: top.word.text,
    solution: top.word.translation,
    group: top.word.sentence,
  };
}

/** Count words that will be due tomorrow. */
export async function getTomorrowCount(
  _reviewKey: string,
  selection: string
): Promise<TomorrowCountResponse> {
  const now = new Date();
  const count = reviewable(await candidateWords(selection)).filter(
    (w) => calculateTomorrowScore(w.status, dayDiff(now, new Date(w.statusChanged))) < 0
  ).length;
  return { count };
}

/** Apply a review answer (absolute status or +/- change). */
export async function updateStatus(
  termId: number,
  status?: number,
  change?: number
): Promise<ReviewStatusResponse> {
  const word = await localDb.words.get(termId);
  if (!word) {
    return { error: 'Term not found' };
  }
  let target: number;
  if (typeof status === 'number') {
    target = status;
  } else if (typeof change === 'number') {
    target = isLearningStatus(word.status)
      ? Math.max(1, Math.min(5, word.status + change))
      : nextStatus(word.status, change > 0);
  } else {
    target = word.status;
  }
  await applyStatus(termId, target);
  return { status: target, controls: '' };
}

/** All reviewable words for table-mode review. */
export async function getTableWords(
  _reviewKey: string,
  selection: string
): Promise<TableWordsResponse | { error: string }> {
  const words = reviewable(await candidateWords(selection));
  const now = new Date();
  const langId = words[0]?.langId ?? (await getCurrentLanguageId());
  const language = await localDb.languages.get(langId);

  const tableWords: TableReviewWord[] = words.map((w) => ({
    id: w.id ?? 0,
    text: w.text,
    translation: w.translation,
    romanization: w.romanization,
    sentence: w.sentence,
    sentenceHtml: w.sentence,
    status: w.status,
    score: calculateScore(w.status, dayDiff(now, new Date(w.statusChanged))),
  }));

  return {
    words: tableWords,
    langSettings: language
      ? langSettings(language)
      : { name: '', dict1Uri: '', dict2Uri: '', translateUri: '', textSize: 100, rtl: false, langCode: '' },
  };
}
