/**
 * Connect/auth page entry for the bundled client.
 *
 * Local-first: with no server configured this page is NOT the first thing the
 * user sees — it redirects straight to the on-device library (seeding starter
 * content on first run). Connecting a server is an optional action, reached with
 * `?connect` (e.g. from Settings), which shows the existing `clientAuth` flow
 * (choose server -> log in/register -> store bearer token) and then navigates to
 * the library. If a token is already stored it skips straight there.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { getApiServer } from '@shared/api/client';
import { bootAppPage, initDataMode, injectConfig } from './boot';
import { pageUrl } from './router';

const forceConnect = new URLSearchParams(window.location.search).has('connect');

if (!getApiServer() && !forceConnect) {
  // First run / no server: become local-first, seed, and open the library.
  void (async () => {
    await initDataMode();
    window.location.replace(pageUrl.library());
  })();
} else {
  injectConfig('client-auth-config', {
    defaultServer: '',
    // clientAuth navigates here after login (and on auto-skip when already
    // signed in). Must be a page the WebView can resolve locally.
    homeUrl: pageUrl.library()
  });

  void bootAppPage({ requireAuth: false });
}
