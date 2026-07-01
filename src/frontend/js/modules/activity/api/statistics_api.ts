/**
 * Statistics bootstrap config fetch.
 *
 * The statistics island needs server-computed chart data the bundle cannot
 * derive locally — the per-language term-status counts (intensity) and the
 * created/activity/known totals over rolling time windows (frequency). The
 * server exposes them at `GET /profile/statistics/config`
 * (StatisticsController@config), mirroring the two JSON blobs the retired
 * `statistics.php` view used to inline.
 *
 * That route is NOT under `/api/v1`, so this uses a base-path-aware raw fetch
 * rather than the api client (same convention as `starter_vocab_api`). It is
 * only reachable in same-origin server mode: the page is server-gated (the
 * statistics are computed from the connected server's database), so offline the
 * island is never mounted and this is never called.
 *
 * @license Unlicense <http://unlicense.org/>
 */

/** Per-language term-status counts for the intensity (stacked bar) chart. */
export interface IntensityLanguageData {
  name: string;
  s1: number;
  s2: number;
  s3: number;
  s4: number;
  s5: number;
  s99: number;
}

/** Created / Activity / Known totals over rolling windows for the line chart. */
export interface FrequencyTotals {
  ct: number; at: number; kt: number;
  cy: number; ay: number; ky: number;
  cw: number; aw: number; kw: number;
  cm: number; am: number; km: number;
  ca: number; aa: number; ka: number;
}

/** Bootstrap config the StatisticsPage island receives as props on mount. */
export interface StatisticsConfig {
  intensity: IntensityLanguageData[];
  frequency: FrequencyTotals;
}

/** A zeroed totals object, used as a safe default when the fetch fails. */
export const ZERO_FREQUENCY_TOTALS: FrequencyTotals = {
  ct: 0, at: 0, kt: 0,
  cy: 0, ay: 0, ky: 0,
  cw: 0, aw: 0, kw: 0,
  cm: 0, am: 0, km: 0,
  ca: 0, aa: 0, ka: 0
};

/** The server's base path (`<meta name="lukaisu-base-path">`), '' at the root. */
function basePath(): string {
  const meta = document.querySelector('meta[name="lukaisu-base-path"]');
  return meta?.getAttribute('content') ?? '';
}

/**
 * Fetch the statistics bootstrap config. Never throws: on any transport/parse
 * failure it returns empty intensity + zeroed totals, so the island still
 * mounts (the streak + calendar sections it fetches itself are unaffected).
 */
export async function fetchStatisticsConfig(): Promise<StatisticsConfig> {
  try {
    const response = await fetch(`${basePath()}/profile/statistics/config`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    });
    if (!response.ok) {
      return { intensity: [], frequency: { ...ZERO_FREQUENCY_TOTALS } };
    }
    const data = (await response.json()) as Partial<StatisticsConfig>;
    return {
      intensity: Array.isArray(data.intensity) ? data.intensity : [],
      frequency: data.frequency ?? { ...ZERO_FREQUENCY_TOTALS }
    };
  } catch {
    return { intensity: [], frequency: { ...ZERO_FREQUENCY_TOTALS } };
  }
}
