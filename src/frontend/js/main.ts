/**
 * Server entry point for the Lukaisu Server application (Alpine-ful).
 *
 * The always-on, Alpine-free bootstrap lives in
 * `shared/boot/frontend_shell.ts` and is shared with the Alpine-free packaged
 * client entry (`client.ts`). THIS entry adds what only the server-rendered PHP
 * pages need: it reads the server-emitted `lukaisu-modules` meta tag and
 * lazy-loads the feature modules (which register Alpine components/stores for
 * those views), registers the `$t`/`$markdown` Alpine magics, and starts Alpine.
 * Server-rendered pages boot this bundle; the local-first client boots
 * `client.ts` instead (see `app/boot.ts`), which ships no Alpine.
 */

// Import Bulma CSS framework
import 'bulma/css/bulma.min.css';

// Import CSS from base directory
import '../css/base/styles.css';
import '../css/base/html5_audio_player.css';
import '../css/base/icons.css';

// Import Alpine.js (server-rendered views still use it)
import Alpine from 'alpinejs';

// i18n helper for the Alpine $t magic
import { t } from '@shared/i18n/translator';

// Shared, Alpine-free bootstrap (infra side-effects, i18n, navbar, token upkeep)
import { runSharedInit, bootI18nAria, mountNavbar } from '@shared/boot/frontend_shell';

// =============================================================================
// DYNAMIC MODULE LOADING + ALPINE.JS INITIALIZATION
// =============================================================================

declare global {
  interface Window {
    Alpine: typeof Alpine;
  }
}

/**
 * Map of dynamically-loadable feature modules.
 *
 * Each key corresponds to a module name that the server can request
 * via the <meta name="lukaisu-modules"> tag.
 */
const moduleMap: Record<string, () => Promise<unknown>> = {
  admin: () => import('@modules/admin'),
  // Only `admin` still has server-rendered pages that need Alpine (the
  // dashboard / backup / wizard / install-demo / server-data / users tools).
  // Everything else — reader/library/review, vocabulary/text/feed/tags, and
  // `language` (its dictionary-management page is now the bundled dictionaries
  // island) — joined `review` and `auth` in the graveyard: each is a bundled
  // Svelte island or a redirected page and its server Views were deleted. A
  // server page that still lists a removed module in its `lukaisu-modules` meta
  // is simply filtered out below (`m in moduleMap`).
};

// Read which modules the current page needs from the server-emitted meta tag
const meta = document.querySelector<HTMLMetaElement>('meta[name="lukaisu-modules"]');
const requestedModules = meta?.content?.split(',').map(m => m.trim()).filter(Boolean) ?? [];

// Start loading all requested modules in parallel
const loaders = requestedModules
  .filter(m => m in moduleMap)
  .map(m => moduleMap[m]());

// Early shared init: async CSS switch, token upkeep, and the server-relative
// auth-expired → /connect redirect. Runs before the modules resolve.
runSharedInit({ serverAuthRedirect: true });

// Wait for all dynamic modules to load, then boot i18n and start Alpine
Promise.all(loaders).then(async () => {
  // i18n first, so the navbar, magics, and first paint all have strings.
  await bootI18nAria();

  // Initialize Alpine.js globally
  window.Alpine = Alpine;

  // Register Alpine.js magic for translations: this.$t('common.save')
  Alpine.magic('t', () => (key: string, params?: Record<string, string | number>) => {
    return t(key, params);
  });

  // Register Alpine.js magic method for inline Markdown parsing
  // Note: Returns plain text since x-html is not CSP-compatible
  // Markdown bold/italic is stripped, only plain text is returned
  Alpine.magic('markdown', () => (text: string) => {
    // For CSP compatibility, strip markdown formatting and return plain text
    // This avoids needing innerHTML which is prohibited in CSP build
    if (!text) return '';
    return text
      .replace(/\*\*([^*]+)\*\*/g, '$1') // Bold
      .replace(/(?<!\*)\*([^*]+)\*(?!\*)/g, '$1') // Italic
      .replace(/~~([^~]+)~~/g, '$1') // Strikethrough
      .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1'); // Links (keep text only)
  });

  // Start Alpine.js
  Alpine.start();

  // Mount the global Svelte navbar island (fetches GET /api/v1/navbar).
  mountNavbar();

  window.LUKAISU_VITE_LOADED = true;

  // Log to console in development
  if (import.meta.env.DEV) {
    console.log('Lukaisu Server Vite bundle loaded (development mode)');
  }
});
