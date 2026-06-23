/**
 * Tests for the offline review-scoring port. Values are derived from the
 * server's `TermStatusService` constants (BASE_SCORES / DECAY_RATES) and the
 * SQL `SCORE_FORMULA_TODAY` / `SCORE_FORMULA_TOMORROW`.
 */

import { describe, it, expect } from 'vitest';
import {
  calculateScore,
  calculateTomorrowScore,
  computeScoreFields,
  dayDiff,
  isDueToday,
  isLearningStatus,
  hasUsableTranslation,
  nextStatus,
} from '@shared/offline/local/review-scoring';

describe('calculateScore', () => {
  it('matches the per-status base score at day 0', () => {
    expect(calculateScore(1, 0)).toBe(0);
    expect(calculateScore(2, 0)).toBeCloseTo(6.9, 5);
    expect(calculateScore(3, 0)).toBe(20);
    expect(calculateScore(4, 0)).toBeCloseTo(46.4, 5);
    expect(calculateScore(5, 0)).toBe(100);
  });

  it('decays linearly per day', () => {
    expect(calculateScore(1, 1)).toBe(-7);
    expect(calculateScore(2, 2)).toBeCloseTo(6.9 - 7, 5);
    expect(calculateScore(3, 10)).toBeCloseTo(20 - 23, 5);
    expect(calculateScore(5, 50)).toBeCloseTo(100 - 70, 5);
  });

  it('clamps at the -125 floor', () => {
    expect(calculateScore(1, 1000)).toBe(-125);
  });

  it('returns 100 for special statuses above 5', () => {
    expect(calculateScore(98, 0)).toBe(100);
    expect(calculateScore(99, 100)).toBe(100);
  });

  it('returns 0 for out-of-range statuses', () => {
    expect(calculateScore(0, 5)).toBe(0);
  });
});

describe('calculateTomorrowScore', () => {
  it('is the score one day further along', () => {
    for (const status of [1, 2, 3, 4, 5]) {
      for (const days of [0, 3, 7]) {
        expect(calculateTomorrowScore(status, days)).toBeCloseTo(
          calculateScore(status, days + 1),
          5
        );
      }
    }
  });
});

describe('dayDiff', () => {
  it('counts calendar days regardless of time of day', () => {
    const since = new Date(2026, 0, 1, 23, 0, 0);
    const now = new Date(2026, 0, 3, 1, 0, 0);
    expect(dayDiff(now, since)).toBe(2);
  });

  it('is 0 within the same calendar day', () => {
    const since = new Date(2026, 0, 1, 8, 0, 0);
    const now = new Date(2026, 0, 1, 20, 0, 0);
    expect(dayDiff(now, since)).toBe(0);
  });
});

describe('due-today selection', () => {
  it('is due once the score goes negative with a real translation', () => {
    expect(
      isDueToday({ status: 1, translation: 'hello', todayScore: -7 })
    ).toBe(true);
  });

  it('is not due while the score is non-negative', () => {
    expect(
      isDueToday({ status: 3, translation: 'hello', todayScore: 5 })
    ).toBe(false);
  });

  it('is not due without a usable translation', () => {
    expect(isDueToday({ status: 1, translation: '*', todayScore: -7 })).toBe(false);
    expect(isDueToday({ status: 1, translation: '', todayScore: -7 })).toBe(false);
  });

  it('excludes ignored/well-known statuses', () => {
    expect(isDueToday({ status: 98, translation: 'x', todayScore: -7 })).toBe(false);
    expect(isDueToday({ status: 99, translation: 'x', todayScore: -7 })).toBe(false);
  });
});

describe('helpers', () => {
  it('classifies learning statuses', () => {
    expect(isLearningStatus(1)).toBe(true);
    expect(isLearningStatus(5)).toBe(true);
    expect(isLearningStatus(98)).toBe(false);
    expect(isLearningStatus(0)).toBe(false);
  });

  it('detects usable translations', () => {
    expect(hasUsableTranslation('cat')).toBe(true);
    expect(hasUsableTranslation('*')).toBe(false);
    expect(hasUsableTranslation('')).toBe(false);
    expect(hasUsableTranslation(null)).toBe(false);
  });

  it('advances and retreats status within bounds', () => {
    expect(nextStatus(1, true)).toBe(2);
    expect(nextStatus(5, true)).toBe(5);
    expect(nextStatus(3, false)).toBe(2);
    expect(nextStatus(1, false)).toBe(1);
    expect(nextStatus(99, true)).toBe(99);
  });

  it('computeScoreFields produces today/tomorrow scores and a shuffle key', () => {
    const changed = new Date(2026, 0, 1);
    const now = new Date(2026, 0, 3);
    const f = computeScoreFields(2, changed, now);
    expect(f.todayScore).toBeCloseTo(calculateScore(2, 2), 5);
    expect(f.tomorrowScore).toBeCloseTo(calculateScore(2, 3), 5);
    expect(f.random).toBeGreaterThanOrEqual(0);
    expect(f.random).toBeLessThan(1);
  });
});
