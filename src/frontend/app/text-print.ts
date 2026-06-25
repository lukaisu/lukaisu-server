/**
 * Plain-print page entry for the bundled client.
 *
 * Replaces the server-rendered print view (`Text/Views/print_alpine.php`) for
 * the plain-print case, mounting the existing `textPrintApp` Alpine component
 * (`modules/text/pages/text_print_app.ts`). Reached from the reader's and
 * library's printer links (`/text/{id}/print-plain`).
 *
 * Scope: **plain print only.** The component supports three modes (plain /
 * annotated / edit), but the annotated "Improved Annotated Text" persists a
 * hand-edited annotation blob that the bundle has no on-device store for — so we
 * pin the mode to `plain` here and serve `GET /texts/{id}/print-items` from the
 * local-first router (the same word/occurrence data the reader uses). The
 * annotated/edit modes stay server-backed; the server's `/text/{id}/print` route
 * keeps handling them through the WebView when a server is connected.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { bootAppPage, injectConfig, fillIdTokens } from './boot';

/** The text to print, from `?text=` (reader/library links) or `?id=`. */
function resolveTextId(): number {
  const params = new URLSearchParams(window.location.search);
  const raw = params.get('text') ?? params.get('id') ?? '0';
  const id = parseInt(raw, 10);
  return Number.isNaN(id) ? 0 : id;
}

async function start(): Promise<void> {
  const textId = resolveTextId();

  // The component reads its mode + text id from #print-config; pin to plain so
  // it never tries to load a (server-only) annotation on-device.
  injectConfig('print-config', { textId, mode: 'plain' });

  // Fill the noprint header's per-text links (Read / Review / Edit text); the
  // link router rewrites them to bundled pages on click.
  fillIdTokens(textId, 0);

  await bootAppPage({ requireAuth: true });
}

void start();
