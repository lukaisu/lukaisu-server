/**
 * CORS-free HTTP for client-side content discovery.
 *
 * The bundled app runs this frontend inside a Capacitor WebView (served from
 * `https://localhost`) where the Capacitor bridge is present. That lets us reach
 * the catalog APIs (Gutendex, Global Digital Library) and Project Gutenberg
 * plain-text files directly via the built-in `CapacitorHttp` plugin — native
 * requests are not subject to the browser's same-origin policy, so no server
 * proxy is needed for these specific, fixed-host sources.
 *
 * Outside the app (a plain browser: `npm run dev`, the server-served PWA) there
 * is no bridge, so we fall back to `window.fetch`. Cross-origin calls then obey
 * CORS — which is why these client-side content sources are only wired in under
 * local-first mode (the packaged app); classic server installs keep using the
 * server's outbound proxy. This mirrors the dev-only CORS caveat already
 * documented for the connect-probe in the lukaisu shell (`src/main.ts`).
 *
 * Scope note: this is deliberately limited to the structured, low-SSRF-risk
 * catalog sources. Arbitrary-URL extraction, RSS and EPUB stay server-enhanced
 * (see both repos' BRIEFING.md seam).
 *
 * @license Unlicense <http://unlicense.org/>
 */

/** Minimal shape of the `CapacitorHttp.request` response we rely on. */
interface CapacitorHttpResponse {
  status: number;
  data: unknown;
}

/** Minimal surface of the built-in `CapacitorHttp` plugin (Capacitor 5+). */
interface CapacitorHttpPlugin {
  request(options: {
    url: string;
    method?: string;
    headers?: Record<string, string>;
    connectTimeout?: number;
    readTimeout?: number;
    responseType?: 'text' | 'json' | 'blob' | 'arraybuffer' | 'document';
  }): Promise<CapacitorHttpResponse>;
}

interface CapacitorGlobal {
  isNativePlatform?: () => boolean;
  Plugins?: { CapacitorHttp?: CapacitorHttpPlugin };
}

declare global {
  interface Window {
    Capacitor?: CapacitorGlobal;
  }
}

/**
 * The native HTTP plugin when running inside the Capacitor app, else null.
 * Guarded so the same bundle stays a plain web app outside the WebView.
 */
function nativeHttp(): CapacitorHttpPlugin | null {
  const cap = typeof window !== 'undefined' ? window.Capacitor : undefined;
  if (cap && cap.isNativePlatform?.() === true && cap.Plugins?.CapacitorHttp) {
    return cap.Plugins.CapacitorHttp;
  }
  return null;
}

/** A normalized response, decoupled from fetch vs. CapacitorHttp. */
export interface FetchResult {
  status: number;
  ok: boolean;
  text: string;
}

const DEFAULT_TIMEOUT_MS = 15000;

/**
 * GET a URL CORS-free when possible. Uses native HTTP inside the app; falls
 * back to `window.fetch` (CORS applies) elsewhere.
 */
export async function corsFreeGet(
  url: string,
  opts: { accept?: string; timeoutMs?: number } = {}
): Promise<FetchResult> {
  const accept = opts.accept ?? 'application/json';
  const timeoutMs = opts.timeoutMs ?? DEFAULT_TIMEOUT_MS;

  const native = nativeHttp();
  if (native) {
    const res = await native.request({
      url,
      method: 'GET',
      headers: { Accept: accept },
      connectTimeout: timeoutMs,
      readTimeout: timeoutMs,
      responseType: 'text',
    });
    // CapacitorHttp may hand back a parsed object for JSON content types even
    // with responseType:'text'; re-serialize so callers always get a string.
    const text =
      typeof res.data === 'string' ? res.data : res.data == null ? '' : JSON.stringify(res.data);
    return { status: res.status, ok: res.status >= 200 && res.status < 300, text };
  }

  const controller = new AbortController();
  const timer: ReturnType<typeof setTimeout> = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const res = await fetch(url, { headers: { Accept: accept }, signal: controller.signal });
    const text = await res.text();
    return { status: res.status, ok: res.ok, text };
  } finally {
    clearTimeout(timer);
  }
}

/** GET and parse JSON, throwing on a non-2xx status or invalid JSON. */
export async function corsFreeGetJson<T>(url: string, timeoutMs?: number): Promise<T> {
  const res = await corsFreeGet(url, { accept: 'application/json', timeoutMs });
  if (!res.ok) {
    throw new Error(`HTTP ${res.status}`);
  }
  return JSON.parse(res.text) as T;
}
