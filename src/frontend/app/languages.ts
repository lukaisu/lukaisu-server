/**
 * Languages list page entry for the bundled client.
 *
 * The mounted Alpine component (`languageList`, from `@modules/language`) loads
 * everything it shows itself in its `init()` — `LanguagesApi.list()` and
 * `getDefinitions()` — so unlike the library/terms pages there is no server
 * config to resolve and inject here. We only flip into local-first mode (and
 * seed on first run) before the component's first API call, then boot.
 *
 * Every endpoint the component reaches — GET /languages, /languages/definitions,
 * POST /languages/{id}/set-default, /refresh, DELETE /languages/{id} — is already
 * served on-device by the local-first router, so the page works with no server.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { bootAppPage, initDataMode } from './boot';

async function start(): Promise<void> {
  // Resolve local-first vs server mode (and seed on first run) before the
  // component mounts and starts calling the API, so this page works offline.
  await initDataMode();
  await bootAppPage({ requireAuth: true });
}

void start();
