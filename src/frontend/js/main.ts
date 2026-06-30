/**
 * Vite entry point for the Lukaisu Server application.
 *
 * This file serves as the main entry point for the Vite build system.
 * It statically imports shared infrastructure and small modules, then
 * dynamically imports feature modules based on the lukaisu-modules meta tag
 * emitted by the server. Alpine.js is started after all dynamic imports
 * have resolved.
 */

// Import Alpine.js
import Alpine from 'alpinejs';

// Svelte mount for the global navbar island
import { mount } from 'svelte';

// Import Bulma CSS framework
import 'bulma/css/bulma.min.css';

// Import CSS from base directory
import '../css/base/styles.css';
import '../css/base/html5_audio_player.css';
import '../css/base/icons.css';

// =============================================================================
// SHARED INFRASTRUCTURE (always loaded)
// =============================================================================

// Shared utilities
import '@shared/utils/html_utils';
import '@shared/utils/cookies';
import '@shared/utils/tts_storage';
import '@shared/utils/ajax_utilities';
import '@shared/utils/ui_utilities';
import '@shared/utils/user_interactions';
import '@shared/utils/simple_interactions';
import '@shared/utils/inline_markdown';

// Shared stores
import '@shared/stores/lukaisu_state';
import '@shared/stores/app_data';

// PWA support
import '@shared/pwa/register';

// Offline support
import '@shared/offline/offline-button';
import '@shared/offline/offline-indicator';

// Shared API client
import '@shared/api/client';

// Shared components (used on every page)
import '@shared/components/modal';
import NavBar from '@shared/components/NavBar.svelte';
import type { NavbarData as NavbarChromeData } from '@shared/components/navbar_renderer';
import '@shared/components/footer';

// Shared i18n
import { bootI18n, t } from '@shared/i18n/translator';

// Shared accessibility
import { initAriaLive } from '@shared/accessibility/aria_live';

// Token session management (packaged/cross-origin clients)
import { maybeRefreshAuthToken, apiGet } from '@shared/api/client';
import { url } from '@shared/utils/url';

// Shared icons
import '@shared/icons/lucide_icons';

// Shared forms (used on most pages)
import '@shared/forms/unloadformcheck';
import '@shared/forms/form_validation';
import '@shared/forms/form_initialization';

// =============================================================================
// ASYNC CSS LOADING (CSP-compliant)
// =============================================================================

// Convert async CSS links from print to all media
// This enables non-render-blocking CSS loading without inline JS
document.querySelectorAll<HTMLLinkElement>('link[data-async-css]').forEach((link) => {
  link.media = 'all';
});

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
  vocabulary: () => import('@modules/vocabulary'),
  text: () => import('@modules/text'),
  review: () => import('@modules/review'),
  feed: () => import('@modules/feed'),
  language: () => import('@modules/language'),
  admin: () => import('@modules/admin'),
  tags: () => import('@modules/tags/pages/tag_list'),
  auth: () => import('@modules/auth'),
  dictionary: () => import('@modules/dictionary/pages/dictionary_import'),
};

// Read which modules the current page needs from the server-emitted meta tag
const meta = document.querySelector<HTMLMetaElement>('meta[name="lukaisu-modules"]');
const requestedModules = meta?.content?.split(',').map(m => m.trim()).filter(Boolean) ?? [];

// Start loading all requested modules in parallel
const loaders = requestedModules
  .filter(m => m in moduleMap)
  .map(m => moduleMap[m]());

// Token-session upkeep for packaged/cross-origin clients. Both are no-ops for
// the same-origin web app (no bearer token), so cookie sessions are unaffected.
// Roll a still-valid token forward if it is nearing expiry...
void maybeRefreshAuthToken();
// ...and when a token is rejected (expired/invalidated), route back to login.
document.addEventListener('lukaisu:auth-expired', () => {
  window.location.assign(url('/connect'));
});

// Wait for all dynamic modules to load, then initialize Alpine
Promise.all(loaders).then(async () => {
  // Initialize i18n: server-injected blob on SSR pages, or API + cache for a
  // shell-free/bundled client (awaited so first paint has strings).
  await bootI18n();

  // Initialize ARIA live regions for screen reader announcements
  initAriaLive();

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

  // Render the global navbar from GET /api/v1/navbar and mount the Svelte island
  // into its placeholder. Fire-and-forget: the rest of the page is already
  // interactive, and pages without a #navbar-root (login, print headers) just
  // no-op. The previously-empty `<div id="navbar-root" data-current-page="…">`
  // is mounted in place (mount appends; the placeholder has no children to clear).
  void (async () => {
    const root = document.getElementById('navbar-root');
    if (!root) {
      return;
    }
    const currentPage = root.getAttribute('data-current-page') ?? '';
    try {
      const res = await apiGet<NavbarChromeData>('/navbar');
      if (!res.data) {
        return;
      }
      mount(NavBar, { target: root, props: { data: res.data, currentPage } });
    } catch (error) {
      console.error('Failed to load navbar:', error);
    }
  })();

  window.LUKAISU_VITE_LOADED = true;

  // Log to console in development
  if (import.meta.env.DEV) {
    console.log('Lukaisu Server Vite bundle loaded (development mode)');
  }
});
