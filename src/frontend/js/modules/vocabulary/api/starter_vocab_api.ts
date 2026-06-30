/**
 * Starter-vocab bootstrap config fetch.
 *
 * The starter-vocab island needs server-only data the bundle cannot compute
 * locally — the language name, whether FrequencyWords data exists for it, and
 * the curated dictionaries filtered for the language. The server exposes them at
 * `GET /languages/{id}/starter-vocab/config` (StarterVocabController@config).
 *
 * That route is NOT under `/api/v1`, so this uses a base-path-aware raw fetch
 * rather than the api client. It is only reachable in same-origin server mode:
 * the page is server-gated (the FrequencyWords import + Wiktionary enrichment
 * need a connected server), so offline the island is never mounted and this is
 * never called.
 *
 * @license Unlicense <http://unlicense.org/>
 */

export interface CuratedDictSource {
  name: string;
  url: string;
  format: string;
  entries: string;
  license: string;
  notes: string;
  directDownload?: boolean;
  dictType?: 'translation' | 'definition';
  targetLanguage?: string;
}

export interface CuratedDictGroup {
  language: string;
  languageName: string;
  sources: CuratedDictSource[];
}

/** Bootstrap config the StarterVocab island reads on mount. */
export interface StarterVocabConfig {
  /** Base-path-correct POST endpoint for frequency-word import. */
  importUrl: string;
  /** Base-path-correct POST endpoint for Wiktionary enrichment. */
  enrichUrl: string;
  langId: number;
  langName: string;
  /** FrequencyWords data exists for this language (Wiktionary source offered). */
  isAvailable: boolean;
  curatedDictionaries: CuratedDictGroup[];
}

/** The server's base path (`<meta name="lukaisu-base-path">`), '' at the root. */
function basePath(): string {
  const meta = document.querySelector('meta[name="lukaisu-base-path"]');
  return meta?.getAttribute('content') ?? '';
}

/**
 * Fetch the starter-vocab bootstrap config for a language, or `null` when the
 * language is unknown or the request fails.
 */
export async function fetchStarterVocabConfig(
  langId: number
): Promise<StarterVocabConfig | null> {
  try {
    const response = await fetch(`${basePath()}/languages/${langId}/starter-vocab/config`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    });
    if (!response.ok) {
      return null;
    }
    const data = (await response.json()) as Partial<StarterVocabConfig>;
    if (typeof data.langName !== 'string') {
      return null;
    }
    return {
      importUrl: data.importUrl ?? '',
      enrichUrl: data.enrichUrl ?? '',
      langId: data.langId ?? langId,
      langName: data.langName,
      isAvailable: data.isAvailable ?? false,
      curatedDictionaries: data.curatedDictionaries ?? []
    };
  } catch {
    return null;
  }
}
