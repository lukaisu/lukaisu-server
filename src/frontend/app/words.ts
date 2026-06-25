/**
 * Terms list (word list) page entry for the bundled client.
 *
 * The PHP page injected `currentlang` (from the `currentlanguage` setting) and
 * `perPage` (from `set-terms-per-page`). Here we resolve the active language
 * from the API the same way `library.ts` does — the server's current language,
 * else the first language. Language switching is handled by the global navbar's
 * switcher; this page just reads back the resulting current language.
 *
 * `perPage` has no clean read-API offline, so it falls back to the component's
 * default (50); the per-page selector persists the user's choice in
 * localStorage after first interaction.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { bootAppPage, initDataMode, injectConfig } from './boot';
import { LanguagesApi } from '@modules/language/api/languages_api';

async function start(): Promise<void> {
  // Resolve local-first vs server mode (and seed on first run) before the first
  // API call, so this page works even when opened directly.
  await initDataMode();

  const res = await LanguagesApi.list();
  const languages = res.data?.languages ?? [];
  const ids = new Set(languages.map((l) => l.id));

  const current = res.data?.currentLanguageId ?? 0;
  const activeId = (current && ids.has(current) && current) || (languages[0]?.id ?? 0);

  injectConfig('word-list-config', { activeLanguageId: activeId, perPage: 50 });

  await bootAppPage({ requireAuth: true });
}

void start();
