/**
 * Feeds page entry for the bundled client — the first *server-enhanced* (Job B)
 * surface.
 *
 * Unlike the Job-A pages (library/reader/words/…), feeds is **not** part of the
 * no-server milestone: RSS/Atom fetching and article extraction need a server's
 * network egress, and none of the `/api/v1/feeds*` endpoints the feed-manager
 * SPA calls are served by the local-first router. So this page is **gated**:
 *
 *   - **server-backed / same-origin** (a server is connected): reveal and mount
 *     the feed-manager SPA (`#feeds-app`); its calls hit the connected server's
 *     `/api/v1/feeds*`, exactly as on the PHP web app.
 *   - **local-first** (packaged app, no server): the SPA's calls would otherwise
 *     fall through to a network that isn't there, so we **remove `#feeds-app`
 *     entirely** before the feed module imports — its auto-init checks for
 *     `#feed-manager-app` (`shouldInitFeedManager`) and so never mounts, and the
 *     fixed-position notifications component (a sibling inside `#feeds-app`) goes
 *     with it — and reveal a "connect a server" notice instead.
 *
 * The removal must happen *before* `bootAppPage` dynamically imports the app
 * bundle (which imports `@modules/feed`, whose `onDomReady` auto-init runs at
 * import time): `initDataMode()` is awaited first, then the DOM is adjusted, then
 * the bundle boots — so by the time the feed module looks for its container it is
 * already gone. The connect action mirrors Settings' optional "Connect a server".
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { bootAppPage, initDataMode } from './boot';
import { pageUrl } from './router';

async function start(): Promise<void> {
  // Resolve local-first vs server mode (and seed on first run) before the app
  // bundle imports the feed module and its auto-init looks for the SPA container.
  const localFirst = await initDataMode();

  if (localFirst) {
    // No server: drop the SPA subtree so nothing mounts or calls /api/v1/feeds*,
    // and surface the "connect a server" notice in its place.
    document.getElementById('feeds-app')?.remove();
    document.getElementById('feeds-offline')?.removeAttribute('hidden');
    document.getElementById('feeds-connect')?.addEventListener('click', () => {
      window.location.assign(pageUrl.connectChooser());
    });
  }

  await bootAppPage({ requireAuth: true });
}

void start();
