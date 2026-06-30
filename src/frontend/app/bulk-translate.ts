/**
 * Bulk-translate page entry for the bundled client — a *server-gated* (Job-B-style)
 * surface backed by the Svelte 5 `BulkTranslate` island (the Alpine→Svelte port of
 * the reader's "Lookup New Words" flow).
 *
 * The flow's work is done by a connected server: the still-unknown words for a
 * text come from a server query, the chosen translations are saved via a PHP form
 * POST (`/word/bulk-translate`), and the in-page translations come from Google's
 * Translate widget — none are served by the local-first router. So this page is
 * gated exactly like feeds.ts / starter-vocab.ts:
 *
 *   - **server-backed / same-origin** (a server is connected): boot i18n, fetch
 *     the island's bootstrap config (`/word/bulk-translate/config`), then mount
 *     the island into `#bulk-translate-root`.
 *   - **local-first** (packaged app, no server): reveal a "connect a server"
 *     notice and mount nothing, so no server-only endpoint is ever requested.
 *
 * The text id + pagination come from the URL
 * (`bulk-translate.html?tid=5&offset=0&sl=en&tl=fr`), preserved by the
 * `/word/bulk-translate` → bundle redirect (BundleController + app/router.ts).
 * Mirrors starter-vocab.ts ordering (`initDataMode` → `bootI18n` → fetch/mount →
 * `bootAppPage`).
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { mount } from 'svelte';
import BulkTranslate from '@modules/vocabulary/pages/BulkTranslate.svelte';
import { fetchBulkTranslateConfig } from '@modules/vocabulary/api/bulk_translate_api';
import { bootAppPage, initDataMode } from './boot';
import { bootI18n } from '@shared/i18n/translator';
import { pageUrl } from './router';

const params = new URLSearchParams(window.location.search);
const tid = parseInt(params.get('tid') ?? '0', 10) || 0;
const offset = parseInt(params.get('offset') ?? '0', 10) || 0;
const sl = params.get('sl') ?? '';
const tl = params.get('tl') ?? '';

async function start(): Promise<void> {
  // Resolve local-first vs server mode before deciding whether to mount.
  const localFirst = await initDataMode();

  if (tid <= 0) {
    // No text to look up unknown words for — go to the library.
    window.location.replace(pageUrl.library());
    return;
  }

  if (localFirst) {
    // No server: surface the "connect a server" notice and mount nothing, so no
    // server-only bulk-translate endpoint is requested.
    document.getElementById('bulk-translate-offline')?.removeAttribute('hidden');
    document.getElementById('bulk-translate-connect')?.addEventListener('click', () => {
      window.location.assign(pageUrl.connectChooser());
    });
  } else {
    // Server connected: load translations, fetch the server-only bootstrap config,
    // then mount the island. A missing/invalid text (null config) bounces to the
    // library.
    await bootI18n();
    const config = await fetchBulkTranslateConfig(tid, offset, sl, tl);
    if (!config) {
      window.location.replace(pageUrl.library());
      return;
    }
    const target = document.getElementById('bulk-translate-root');
    if (target) {
      mount(BulkTranslate, { target, props: { config } });
    }
  }

  // Boot the shared shell (navbar, link router, Alpine). Runs after the island is
  // mounted (server mode); the two manage disjoint DOM regions.
  await bootAppPage({ requireAuth: true });
}

void start();
