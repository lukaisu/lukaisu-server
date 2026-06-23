/**
 * Shared bootstrap for every bundled ("Model B") page.
 *
 * Each page's TS entry (connect/library/read) injects its runtime config blob,
 * then calls {@link bootAppPage}, which:
 *   1. gates pages that need a session (bounce to connect when no token),
 *   2. routes a mid-session `lukaisu:auth-expired` back to the local connect page
 *      (instead of main.ts's server-relative `/connect`, which 404s here),
 *   3. installs the link router (server hrefs -> local pages / remote UI),
 *   4. hands off to the existing app bundle (`src/frontend/js/main.ts`), which
 *      reads `<meta name="lukaisu-modules">`, boots i18n, and starts Alpine.
 *
 * Server URL + bearer token already live in localStorage (set by the connect
 * flow), and `@shared/api/client` reads them automatically — so once connected,
 * every page talks to the chosen remote server with no extra wiring.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { getApiServer, getAuthToken, isAuthOptional } from '@shared/api/client';
import { setLocalFirst } from '@shared/offline/local/router';
import { seedIfNeeded } from '@shared/offline/local/repositories';
import { installLinkRouter, pageUrl } from './router';

export interface BootOptions {
  /** Surfaces require a signed-in session; the connect page does not. */
  requireAuth: boolean;
}

/**
 * Choose the data source for this launch and seed the on-device DB on first
 * run. With no server configured the app is fully local-first (the F-Droid /
 * offline case); once a server is connected it falls back to server-backed mode
 * (local⇄server sync is a later milestone). Idempotent — safe on every page.
 *
 * @returns true when running fully on-device (no server).
 */
export async function initDataMode(): Promise<boolean> {
  const hasServer = getApiServer() !== '';
  setLocalFirst(!hasServer);
  if (hasServer) {
    return false;
  }
  try {
    await seedIfNeeded();
  } catch (error) {
    console.error('[lukaisu] local seed failed', error);
  }
  return true;
}

/**
 * Inject (or replace) a JSON config blob the way the PHP views emit it, so the
 * Alpine components read their runtime config from the same `<script>` ids.
 */
export function injectConfig(id: string, config: unknown): void {
  let el = document.getElementById(id);
  if (!el) {
    const script = document.createElement('script');
    script.type = 'application/json';
    script.id = id;
    document.body.appendChild(script);
    el = script;
  }
  el.textContent = JSON.stringify(config);
}

/** Replace `{{TEXT_ID}}` / `{{LANG_ID}}` tokens left in prerendered hrefs. */
export function fillIdTokens(textId: number, langId: number): void {
  document.querySelectorAll<HTMLAnchorElement>('a[href*="{{"]').forEach((a) => {
    const href = a.getAttribute('href');
    if (!href) return;
    a.setAttribute(
      'href',
      href.replace(/\{\{TEXT_ID\}\}/g, String(textId)).replace(/\{\{LANG_ID\}\}/g, String(langId))
    );
  });
}

export async function bootAppPage(opts: BootOptions): Promise<void> {
  // 0. Pick local-first vs server-backed mode and seed on first run.
  const localFirst = await initDataMode();

  // 1. Auth gate (server mode only). Local-first owns its data on-device, so it
  //    never needs a session. In server mode, a bearer token (multi-user) OR a
  //    server known to need no login (single-user self-host) both pass.
  if (opts.requireAuth && !localFirst && !getAuthToken() && !isAuthOptional()) {
    window.location.replace(pageUrl.connect());
    return;
  }

  // 2. Re-route auth-expired to the local connect page. Registered before
  //    main.ts adds its own (server-relative) handler; stopImmediatePropagation
  //    keeps that one — which would 404 in the bundle — from also firing.
  document.addEventListener('lukaisu:auth-expired', (event) => {
    event.stopImmediatePropagation();
    window.location.replace(pageUrl.connect());
  });

  // 3. Rewrite in-app links.
  installLinkRouter();

  // 4. Hand off to the shared app bundle (reads the static lukaisu-modules meta,
  //    boots i18n from the API/cache, starts Alpine). Dynamic import so this
  //    setup runs first.
  await import('@/main');
}
