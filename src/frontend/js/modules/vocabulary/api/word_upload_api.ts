/**
 * Word-upload bootstrap config fetch.
 *
 * The word-upload island needs server-only data the bundle cannot compute
 * locally — the current language (name + id), whether FrequencyWords data exists
 * for it, the curated dictionaries registry, the base-path-correct POST endpoints
 * (frequency import / enrichment / the file-upload action), and the default
 * translation delimiter. The server exposes them at `GET /word/upload/config`
 * (TermImportController@uploadConfig).
 *
 * That route is NOT under `/api/v1`, so this uses a base-path-aware raw fetch
 * rather than the api client (mirroring starter_vocab_api / bulk_translate_api).
 * It is only reachable in same-origin server mode: the page is server-gated (the
 * file import + frequency import + curated import all need a connected server),
 * so offline the island is never mounted and this is never called.
 *
 * @license Unlicense <http://unlicense.org/>
 */

/** A curated dictionary source entry (one downloadable dictionary). */
export interface CuratedDictSource {
  name: string;
  url: string;
  format: string;
  entries: string;
  license: string;
  notes: string;
  directDownload?: boolean;
  dictType?: 'translation' | 'definition' | string;
  targetLanguage?: string;
}

/** A curated dictionary language group. */
export interface CuratedDictGroup {
  language: string;
  languageName: string;
  sources: CuratedDictSource[];
}

/** Bootstrap config the WordUpload island reads on mount. */
export interface WordUploadConfig {
  /** Base-path-correct POST endpoint the manual upload form submits to. */
  uploadUrl: string;
  /** Current language id (0 when none selected). */
  langId: number;
  /** Current language name ('' when none selected). */
  langName: string;
  /** FrequencyWords data exists for this language (Wiktionary source offered). */
  isFrequencyAvailable: boolean;
  /** Base-path-correct POST endpoint for frequency-word import ('' when no lang). */
  importUrl: string;
  /** Base-path-correct POST endpoint for Wiktionary enrichment ('' when no lang). */
  enrichUrl: string;
  /** Default translation-delimiter (for the merge/update import modes). */
  translationDelimiter: string;
  /** All curated dictionary groups (filtered/searched client-side). */
  curatedDictionaries: CuratedDictGroup[];
}

/** The server's base path (`<meta name="lukaisu-base-path">`), '' at the root. */
function basePath(): string {
  const meta = document.querySelector('meta[name="lukaisu-base-path"]');
  return meta?.getAttribute('content') ?? '';
}

/**
 * Fetch the word-upload bootstrap config, or `null` when the request fails.
 */
export async function fetchWordUploadConfig(): Promise<WordUploadConfig | null> {
  try {
    const response = await fetch(`${basePath()}/word/upload/config`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    });
    if (!response.ok) {
      return null;
    }
    const data = (await response.json()) as Partial<WordUploadConfig>;
    return {
      uploadUrl: data.uploadUrl ?? '/word/upload',
      langId: data.langId ?? 0,
      langName: data.langName ?? '',
      isFrequencyAvailable: data.isFrequencyAvailable ?? false,
      importUrl: data.importUrl ?? '',
      enrichUrl: data.enrichUrl ?? '',
      translationDelimiter: data.translationDelimiter ?? '',
      curatedDictionaries: data.curatedDictionaries ?? []
    };
  } catch {
    return null;
  }
}
