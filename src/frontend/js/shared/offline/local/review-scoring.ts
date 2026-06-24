/**
 * Review scheduling — TypeScript port of the server's `TermStatusService`
 * spaced-repetition scoring (`today_score` / `tomorrow_score`).
 *
 * A word's "score" decays linearly from a per-status base; when it drops below
 * zero the word is due. Higher statuses decay slower, so they resurface less
 * often. This mirrors the SQL/PHP formulas exactly so offline review picks the
 * same words the server would.
 *
 * @license Unlicense <http://unlicense.org/>
 */

/** Learning statuses that participate in review (1 = new … 5 = learned). */
export const MIN_LEARNING_STATUS = 1;
export const MAX_LEARNING_STATUS = 5;
/** Special statuses excluded from review. */
export const STATUS_IGNORED = 98;
export const STATUS_WELL_KNOWN = 99;

/** Score at day 0 for each learning status. */
const BASE_SCORES: Record<number, number> = {
  1: 0.0,
  2: 6.9,
  3: 20.0,
  4: 46.4,
  5: 100.0,
};

/** Points lost per elapsed day for each learning status. */
const DECAY_RATES: Record<number, number> = {
  1: 7.0,
  2: 3.5,
  3: 2.3,
  4: 1.75,
  5: 1.4,
};

/** Floor below which scores are clamped (matches the server). */
const SCORE_FLOOR = -125;

/**
 * Calendar-day difference between two instants (date parts only), matching
 * MySQL `DATEDIFF`. Rounded to avoid DST drift.
 */
export function dayDiff(now: Date, since: Date): number {
  const a = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
  const b = new Date(
    since.getFullYear(),
    since.getMonth(),
    since.getDate()
  ).getTime();
  return Math.round((a - b) / 86_400_000);
}

/**
 * Score for a word `daysSinceChange` days after its status last changed.
 * Statuses above 5 (ignored / well-known) are never due (score 100).
 */
export function calculateScore(status: number, daysSinceChange: number): number {
  if (status > MAX_LEARNING_STATUS) {
    return 100;
  }
  const base = BASE_SCORES[status];
  const decay = DECAY_RATES[status];
  if (base === undefined || decay === undefined) {
    return 0;
  }
  return Math.max(SCORE_FLOOR, base - decay * daysSinceChange);
}

/** Tomorrow's score is simply today's score one day further along. */
export function calculateTomorrowScore(
  status: number,
  daysSinceChange: number
): number {
  return calculateScore(status, daysSinceChange + 1);
}

/** Scores + shuffle key to persist when a word's status changes. */
export interface ScoreFields {
  todayScore: number;
  tomorrowScore: number;
  random: number;
}

/**
 * Compute the score fields for a word given its status and when that status was
 * set. `now` defaults to the current time; `random` is a fresh shuffle key.
 */
export function computeScoreFields(
  status: number,
  statusChanged: Date,
  now: Date = new Date()
): ScoreFields {
  const days = dayDiff(now, statusChanged);
  return {
    todayScore: calculateScore(status, days),
    tomorrowScore: calculateTomorrowScore(status, days),
    random: Math.random(),
  };
}

/** True if `status` is a learning status (1–5) that can appear in review. */
export function isLearningStatus(status: number): boolean {
  return status >= MIN_LEARNING_STATUS && status <= MAX_LEARNING_STATUS;
}

/** A word has a usable translation if it is neither empty nor the `*` stub. */
export function hasUsableTranslation(translation: string | null | undefined): boolean {
  return translation != null && translation !== '' && translation !== '*';
}

/**
 * Whether a word is due for review today: a learning status, a real
 * translation, and a negative today-score.
 */
export function isDueToday(
  word: { status: number; translation?: string | null; todayScore: number },
  ): boolean {
  return (
    isLearningStatus(word.status) &&
    hasUsableTranslation(word.translation) &&
    word.todayScore < 0
  );
}

/**
 * New status after a review answer. A correct answer advances toward 5; an
 * incorrect answer steps back toward 1. Special statuses (98/99) are left
 * unchanged.
 */
export function nextStatus(status: number, correct: boolean): number {
  if (!isLearningStatus(status)) {
    return status;
  }
  return correct
    ? Math.min(MAX_LEARNING_STATUS, status + 1)
    : Math.max(MIN_LEARNING_STATUS, status - 1);
}
