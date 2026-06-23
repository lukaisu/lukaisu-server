/**
 * Local API router — the seam that lets the existing rendering code keep calling
 * `/api/v1` while the data is served from the on-device database.
 *
 * `apiGet/apiPost/apiPut/apiDelete/apiPostForm` in the shared API client consult
 * `routeLocal` first. When local-first mode is enabled and an endpoint is owned
 * by the local data layer, the matching repository handles it and the request
 * never touches the network; otherwise the call falls through to HTTP (used for
 * server-enhanced features like CJK tokenization, TTS and content discovery).
 *
 * Local-first mode defaults to OFF so existing same-origin/server installs (and
 * the test suite, which mocks `fetch`) are unchanged; the packaged offline app
 * turns it on at boot via {@link setLocalFirst}.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import {
  listLanguages,
  getDefinitions,
  getLanguage,
  getLanguageStats,
  createLanguage,
  updateLanguage,
  deleteLanguage,
  setDefaultLanguage,
} from './repositories/languages';
import {
  getTextWords,
  createText,
  getStatistics,
  bulkAction as textBulkAction,
  markAllWellKnown,
  markAllIgnored,
  reparseLanguage,
} from './repositories/texts';
import {
  setStatus,
  incrementStatus,
  createQuick,
  createFull,
  updateFull,
  updateTranslation,
  addWithTranslation,
  deleteTerm,
  getForEdit,
} from './repositories/terms';
import {
  getReviewConfig,
  getNextWord,
  getTomorrowCount,
  updateStatus as reviewUpdateStatus,
  getTableWords,
} from './repositories/review';
import {
  getList,
  getFilterOptions,
  inlineEdit,
  bulkAction as wordsBulkAction,
} from './repositories/words';
import { getNavbarData } from './repositories/navbar';
import { getSentencesWithTerm } from './repositories/sentences';
import { setSetting, setCurrentLanguageId } from './repositories/settings';
import type {
  TextCreateRequest,
} from '@modules/text/api/texts_api';
import type {
  LanguageCreateRequest,
} from '@modules/language/api/languages_api';
import type {
  TermCreateFullRequest,
  TermUpdateFullRequest,
} from '@modules/vocabulary/api/terms_api';
import type { WordListFilters } from '@modules/vocabulary/api/words_api';

const LOCAL_FIRST_KEY = 'lukaisu.localFirst';

let localFirstOverride: boolean | null = null;

function readLocalFirst(): boolean {
  try {
    return localStorage.getItem(LOCAL_FIRST_KEY) === '1';
  } catch {
    return false;
  }
}

/** Whether the on-device data layer is the default source. */
export function isLocalFirst(): boolean {
  return localFirstOverride ?? readLocalFirst();
}

/** Enable/disable local-first mode (persisted across launches). */
export function setLocalFirst(enabled: boolean): void {
  localFirstOverride = enabled;
  try {
    if (enabled) {
      localStorage.setItem(LOCAL_FIRST_KEY, '1');
    } else {
      localStorage.removeItem(LOCAL_FIRST_KEY);
    }
  } catch {
    // localStorage unavailable: the in-memory override still applies.
  }
}

/** Result of attempting to serve a request locally. */
export interface LocalRouteResult {
  handled: boolean;
  data?: unknown;
  error?: string;
}

type Payload = Record<string, unknown> | undefined;

function num(value: unknown): number {
  return typeof value === 'number' ? value : parseInt(String(value ?? ''), 10);
}

function str(value: unknown): string {
  return value == null ? '' : String(value);
}

/** Wrap a repository result, surfacing bare `{ error }` objects as errors. */
function wrap(result: unknown): LocalRouteResult {
  if (result && typeof result === 'object' && 'error' in result) {
    const keys = Object.keys(result as Record<string, unknown>);
    if (keys.length === 1) {
      return { handled: true, error: str((result as { error: unknown }).error) };
    }
  }
  return { handled: true, data: result };
}

const NOT_HANDLED: LocalRouteResult = { handled: false };

async function routeGet(path: string, p: Record<string, unknown>): Promise<LocalRouteResult> {
  if (path === '/navbar') {
    return wrap(await getNavbarData());
  }
  if (path === '/languages') {
    return wrap(await listLanguages());
  }
  if (path === '/languages/definitions') {
    return wrap(getDefinitions());
  }
  let m = path.match(/^\/languages\/(\d+)\/stats$/);
  if (m) {
    return wrap(await getLanguageStats(num(m[1])));
  }
  m = path.match(/^\/languages\/(\d+)$/);
  if (m) {
    return wrap(await getLanguage(num(m[1])));
  }
  m = path.match(/^\/texts\/(\d+)\/words$/);
  if (m) {
    return wrap(await getTextWords(num(m[1])));
  }
  if (path === '/texts/statistics') {
    const ids = str(p.text_ids)
      .split(',')
      .map((s) => parseInt(s, 10))
      .filter((n) => !Number.isNaN(n));
    return wrap(await getStatistics(ids));
  }
  if (path === '/terms/for-edit') {
    return wrap(
      await getForEdit(num(p.term_id), num(p.ord), p.wid != null ? num(p.wid) : undefined)
    );
  }
  m = path.match(/^\/sentences-with-term\/(\d+)$/);
  if (m) {
    return wrap(await getSentencesWithTerm(num(p.language_id), str(p.term_lc), num(m[1])));
  }
  if (path === '/sentences-with-term') {
    return wrap(await getSentencesWithTerm(num(p.language_id), str(p.term_lc)));
  }
  if (path === '/terms/list') {
    return wrap(await getList(p as WordListFilters));
  }
  if (path === '/terms/filter-options') {
    return wrap(await getFilterOptions(p.language_id != null ? num(p.language_id) : null));
  }
  if (path === '/review/config') {
    return wrap(
      await getReviewConfig({
        lang: p.lang != null ? num(p.lang) : undefined,
        text: p.text != null ? num(p.text) : undefined,
        selection: p.selection != null ? num(p.selection) : undefined,
      })
    );
  }
  if (path === '/review/next-word') {
    return wrap(
      await getNextWord({
        reviewKey: str(p.review_key),
        selection: str(p.selection),
        wordMode: p.word_mode === true || p.word_mode === 'true',
        lgId: num(p.language_id),
        wordRegex: str(p.word_regex),
        type: num(p.type),
      })
    );
  }
  if (path === '/review/tomorrow-count') {
    return wrap(await getTomorrowCount(str(p.review_key), str(p.selection)));
  }
  if (path === '/review/table-words') {
    return wrap(await getTableWords(str(p.review_key), str(p.selection)));
  }
  return NOT_HANDLED;
}

async function routePost(path: string, p: Record<string, unknown>): Promise<LocalRouteResult> {
  if (path === '/settings') {
    const key = str(p.key);
    const value = str(p.value);
    if (key === 'currentlanguage') {
      // The navbar's language switcher (setLangAsync) posts the chosen id here.
      await setCurrentLanguageId(value ? num(value) : 0);
    } else {
      await setSetting(key, value);
    }
    return { handled: true, data: { message: 'Setting updated' } };
  }
  if (path === '/languages') {
    return wrap(await createLanguage(p as unknown as LanguageCreateRequest));
  }
  let m = path.match(/^\/languages\/(\d+)\/set-default$/);
  if (m) {
    return wrap(await setDefaultLanguage(num(m[1])));
  }
  m = path.match(/^\/languages\/(\d+)\/refresh$/);
  if (m) {
    const added = await reparseLanguage(num(m[1]));
    return {
      handled: true,
      data: {
        success: true,
        sentencesDeleted: 0,
        textItemsDeleted: 0,
        sentencesAdded: added,
        textItemsAdded: added,
      },
    };
  }
  if (path === '/texts') {
    return wrap(await createText(p as unknown as TextCreateRequest));
  }
  if (path === '/terms') {
    return wrap(
      await addWithTranslation(str(p.text), num(p.language_id), str(p.translation))
    );
  }
  if (path === '/terms/quick') {
    return wrap(await createQuick(num(p.text_id), num(p.position), num(p.status) as 98 | 99));
  }
  if (path === '/terms/full') {
    return wrap(await createFull(p as unknown as TermCreateFullRequest));
  }
  m = path.match(/^\/terms\/(\d+)\/status\/(.+)$/);
  if (m) {
    const id = num(m[1]);
    const target = m[2];
    if (target === 'up' || target === 'down') {
      return wrap(await incrementStatus(id, target));
    }
    return wrap(await setStatus(id, num(target)));
  }
  return NOT_HANDLED;
}

async function routePut(path: string, p: Record<string, unknown>): Promise<LocalRouteResult> {
  let m = path.match(/^\/terms\/(\d+)\/translation$/);
  if (m) {
    return wrap(await updateTranslation(num(m[1]), str(p.translation)));
  }
  m = path.match(/^\/terms\/(\d+)\/inline-edit$/);
  if (m) {
    return wrap(
      await inlineEdit(
        num(m[1]),
        str(p.field) as 'translation' | 'romanization',
        str(p.value)
      )
    );
  }
  if (path === '/terms/bulk-action') {
    return wrap(
      await wordsBulkAction(
        (p.ids as number[]) ?? [],
        str(p.action),
        p.data != null ? str(p.data) : undefined
      )
    );
  }
  if (path === '/texts/bulk-action') {
    return wrap(
      await textBulkAction(str(p.action) as 'archive' | 'delete', (p.ids as number[]) ?? [])
    );
  }
  m = path.match(/^\/texts\/(\d+)\/display-mode$/);
  if (m) {
    return { handled: true, data: { updated: true } };
  }
  m = path.match(/^\/texts\/(\d+)\/mark-all-wellknown$/);
  if (m) {
    return wrap(await markAllWellKnown(num(m[1])));
  }
  m = path.match(/^\/texts\/(\d+)\/mark-all-ignored$/);
  if (m) {
    return wrap(await markAllIgnored(num(m[1])));
  }
  if (path === '/review/status') {
    return wrap(
      await reviewUpdateStatus(
        num(p.term_id),
        p.status != null ? num(p.status) : undefined,
        p.change != null ? num(p.change) : undefined
      )
    );
  }
  m = path.match(/^\/languages\/(\d+)$/);
  if (m) {
    return wrap(await updateLanguage(num(m[1]), p as unknown as LanguageCreateRequest));
  }
  m = path.match(/^\/terms\/(\d+)$/);
  if (m) {
    return wrap(await updateFull(num(m[1]), p as unknown as TermUpdateFullRequest));
  }
  return NOT_HANDLED;
}

async function routeDelete(path: string): Promise<LocalRouteResult> {
  let m = path.match(/^\/terms\/(\d+)$/);
  if (m) {
    return wrap(await deleteTerm(num(m[1])));
  }
  m = path.match(/^\/languages\/(\d+)$/);
  if (m) {
    return wrap(await deleteLanguage(num(m[1])));
  }
  return NOT_HANDLED;
}

/**
 * Try to serve an API call from the local database. Returns `{ handled:false }`
 * when local-first mode is off or the endpoint is not owned locally, so the
 * caller falls back to the network.
 */
export async function routeLocal(
  method: 'GET' | 'POST' | 'PUT' | 'DELETE',
  endpoint: string,
  payload: Payload
): Promise<LocalRouteResult> {
  if (!isLocalFirst()) {
    return NOT_HANDLED;
  }
  const path = endpoint.split('?')[0];
  const p = payload ?? {};
  try {
    switch (method) {
      case 'GET':
        return await routeGet(path, p);
      case 'POST':
        return await routePost(path, p);
      case 'PUT':
        return await routePut(path, p);
      case 'DELETE':
        return await routeDelete(path);
      default:
        return NOT_HANDLED;
    }
  } catch (error) {
    return { handled: true, error: String(error) };
  }
}
