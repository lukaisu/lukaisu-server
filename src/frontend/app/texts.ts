/**
 * Archived-texts page entry for the bundled client.
 *
 * Replaces the server-rendered archived-texts view
 * (`Text/Views/archived_list.php`), mounting the same `archivedTextsGroupedApp`
 * Alpine component. The "active manage" half of the texts-management UI
 * (`edit_list.php`) is already bundled as `library.html` (it mounts the same
 * `textsGroupedApp` component), so this page covers the archived half; the two
 * cross-link through the action-card "Active Texts" / "Archived Texts" buttons,
 * matching the server's two-page UX.
 *
 * The PHP view injected `activeLanguageId` (default-expanded language) from the
 * `currentlanguage` setting. We resolve it from the API the same way
 * `library.ts` does — the server's current language, else the first language —
 * and re-inject `archived-texts-grouped-config` so the component reads it from
 * the same `<script>` id. Per-text archive/unarchive/delete are served on-device
 * by the local-first router (the component routes them through the API seam when
 * `isLocalFirst`).
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

  injectConfig('archived-texts-grouped-config', { activeLanguageId: activeId });

  await bootAppPage({ requireAuth: true });
}

void start();
