/**
 * Bulk-translate bootstrap config fetch.
 *
 * The bulk-translate island needs server-only data the bundle cannot compute
 * locally — the text's dictionaries and the page of still-unknown words to
 * translate. The server exposes them at `GET /word/bulk-translate/config`
 * (TermImportController@config).
 *
 * That route is NOT under `/api/v1`, so this uses a base-path-aware raw fetch
 * rather than the api client. It is only reachable in same-origin server mode:
 * the page is server-gated (the unknown-word query, the save POST, and the
 * Google Translate widget all need a connected server), so offline the island is
 * never mounted and this is never called.
 *
 * @license Unlicense <http://unlicense.org/>
 */

/** One unknown word the user can translate and save. */
export interface BulkTranslateTerm {
  word: string;
  languageId: number;
}

/** Dictionary URIs for the text's language (D1 / D2 / translator links). */
export interface BulkTranslateDictionaries {
  dict1: string;
  dict2: string;
  translate: string;
}

/** Bootstrap config the BulkTranslate island reads on mount. */
export interface BulkTranslateConfig {
  /** Base-path-correct POST endpoint the form submits the chosen terms to. */
  saveUrl: string;
  tid: number;
  /** Source language code for the Google Translate widget (page language). */
  sourceLanguage: string | null;
  /** Target language code for the Google Translate widget (included language). */
  targetLanguage: string | null;
  offset: number;
  dictionaries: BulkTranslateDictionaries;
  terms: BulkTranslateTerm[];
  /** Offset to carry for the next batch, or null when this is the last page. */
  nextOffset: number | null;
}

/** The server's base path (`<meta name="lukaisu-base-path">`), '' at the root. */
function basePath(): string {
  const meta = document.querySelector('meta[name="lukaisu-base-path"]');
  return meta?.getAttribute('content') ?? '';
}

/**
 * Fetch the bulk-translate bootstrap config for a text/offset, or `null` when
 * the text is unknown or the request fails.
 */
export async function fetchBulkTranslateConfig(
  tid: number,
  offset: number,
  sl: string,
  tl: string
): Promise<BulkTranslateConfig | null> {
  try {
    const params = new URLSearchParams({
      tid: String(tid),
      offset: String(offset)
    });
    if (sl !== '') {
      params.set('sl', sl);
    }
    if (tl !== '') {
      params.set('tl', tl);
    }
    const response = await fetch(`${basePath()}/word/bulk-translate/config?${params.toString()}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    });
    if (!response.ok) {
      return null;
    }
    const data = (await response.json()) as Partial<BulkTranslateConfig>;
    if (!Array.isArray(data.terms)) {
      return null;
    }
    const dict = data.dictionaries ?? { dict1: '', dict2: '', translate: '' };
    return {
      saveUrl: data.saveUrl ?? '/word/bulk-translate',
      tid: data.tid ?? tid,
      sourceLanguage: data.sourceLanguage ?? null,
      targetLanguage: data.targetLanguage ?? null,
      offset: data.offset ?? offset,
      dictionaries: {
        dict1: dict.dict1 ?? '',
        dict2: dict.dict2 ?? '',
        translate: dict.translate ?? ''
      },
      terms: data.terms,
      nextOffset: data.nextOffset ?? null
    };
  } catch {
    return null;
  }
}
