/**
 * Client-side navigation for the bundled ("Model B") client.
 *
 * In the bundled app there is no PHP server serving pages: the reader, library
 * and connect surfaces are static HTML shipped in the APK and served from the
 * WebView root. The existing Lukaisu Server frontend, however, emits server-relative links
 * (e.g. `/text/42/read`, `/texts`, `/connect`). This module rewrites those at
 * click time:
 *
 *   - links to a surface we bundle      -> the local page (read/library/connect)
 *   - other in-app server links         -> the *remote* server's web UI
 *     (edit, review, imports, settings…), so they keep working via the WebView
 *     even though they are not bundled yet (the hybrid that the roadmap calls
 *     "partition bundled-vs-WebView routes")
 *   - absolute URLs / target=_blank     -> left untouched (open as-is)
 *
 * @license Unlicense <http://unlicense.org/>
 */

import { getApiServer } from '@shared/api/client';

/** Builders for the bundled pages (relative to the current page at the root). */
export const pageUrl = {
  connect(): string {
    return 'index.html';
  },
  /**
   * The connect/server-picker page forced open (`?connect`), skipping the
   * local-first auto-redirect to the library. This is the entry point for the
   * optional "Connect a server" action in Settings.
   */
  connectChooser(): string {
    return 'index.html?connect';
  },
  home(): string {
    return 'home.html';
  },
  library(): string {
    return 'library.html';
  },
  read(textId: number | string, langId?: number | string): string {
    const lang = langId ? `&lang=${encodeURIComponent(String(langId))}` : '';
    return `read.html?text=${encodeURIComponent(String(textId))}${lang}`;
  },
  review(params: URLSearchParams): string {
    const query = params.toString();
    return query ? `review.html?${query}` : 'review.html';
  },
  languages(): string {
    return 'languages.html';
  },
  newLanguage(): string {
    return 'language.html';
  },
  editLanguage(langId: number | string): string {
    return `language-edit.html?id=${encodeURIComponent(String(langId))}`;
  },
  starterVocab(langId: number | string): string {
    return `starter-vocab.html?lang=${encodeURIComponent(String(langId))}`;
  },
  newText(): string {
    return 'text.html';
  },
  archivedTexts(): string {
    return 'texts.html';
  },
  editText(textId: number | string): string {
    return `text-edit.html?id=${encodeURIComponent(String(textId))}`;
  },
  textCheck(): string {
    return 'text-check.html';
  },
  tags(): string {
    return 'tags.html';
  },
  feeds(): string {
    return 'feeds.html';
  },
  dictionaries(langId?: number | string): string {
    return langId != null
      ? `dictionaries.html?lang=${encodeURIComponent(String(langId))}`
      : 'dictionaries.html';
  },
  settings(): string {
    return 'settings.html';
  },
  print(textId: number | string): string {
    return `text-print.html?text=${encodeURIComponent(String(textId))}`;
  },
  words(query = ''): string {
    return query ? `words.html?${query}` : 'words.html';
  },
  word(termId: number | string): string {
    return `word.html?id=${encodeURIComponent(String(termId))}`;
  }
};

/**
 * Map a server-relative path to a bundled page URL, or null if no bundled page
 * covers it (caller falls back to the remote server).
 */
export function bundledPageFor(path: string): string | null {
  // Strip query/hash for matching; keep the original for param extraction.
  const [pathname, query = ''] = path.split('?');

  // The home route ('/' and '/index.php') renders the bundled dashboard, the
  // same as the server. The texts list keeps its own route ('/texts' ->
  // library), so the navbar logo lands on the dashboard while the Texts nav goes
  // to the library — matching the server's two surfaces.
  if (pathname === '/' || pathname === '/index.php') {
    return pageUrl.home();
  }
  if (pathname === '/texts') {
    return pageUrl.library();
  }
  if (pathname === '/connect') {
    return pageUrl.connect();
  }
  // Create surfaces: the navbar "+" and library "new text" links point here.
  // The server-rendered forms can't run offline, so route to the bundled
  // API-client forms instead.
  if (pathname === '/languages/new') {
    return pageUrl.newLanguage();
  }
  if (pathname === '/texts/new') {
    return pageUrl.newText();
  }
  // Archived texts list — reached from the active list's "Archived Texts" action
  // card (/text/archived?query=&page=1) and the navbar.
  if (pathname === '/text/archived') {
    return pageUrl.archivedTexts();
  }
  // Parse-preview tool, reached from the navbar's "Check a text" link
  // (/text/check). The server form does a native POST; the bundle parses
  // on-device instead.
  if (pathname === '/text/check') {
    return pageUrl.textCheck();
  }
  // Single-text edit form, reached from both lists' Edit links: /texts/{id}/edit
  // (active, from library.html) and /text/archived/{id}/edit (archived, from
  // texts.html) both render the same bundled form (it detects archived itself).
  const textEditMatch = pathname.match(/^\/texts\/(\d+)\/edit$/)
    ?? pathname.match(/^\/text\/archived\/(\d+)\/edit$/);
  if (textEditMatch) {
    return pageUrl.editText(textEditMatch[1]);
  }
  // Languages list, and the per-language settings form reached from its Edit
  // links (/languages/{id}/edit -> language-edit.html?id={id}).
  if (pathname === '/languages') {
    return pageUrl.languages();
  }
  const langEditMatch = pathname.match(/^\/languages\/(\d+)\/edit$/);
  if (langEditMatch) {
    return pageUrl.editLanguage(langEditMatch[1]);
  }
  // Starter-vocab offer (shown after creating a language): /languages/{id}/starter-vocab
  // -> the bundled Svelte StarterVocab island, carrying the language id as ?lang=.
  // Server-gated like feeds (the FrequencyWords import + Wiktionary enrichment need
  // a connected server); offline it shows a "connect a server" notice.
  const starterVocabMatch = pathname.match(/^\/languages\/(\d+)\/starter-vocab$/);
  if (starterVocabMatch) {
    return pageUrl.starterVocab(starterVocabMatch[1]);
  }
  // Single-term edit form: /words/{id}/edit -> word.html?id={id}. Must precede
  // the list mapping below (which matches the literal /words/edit, not this).
  const wordEditMatch = pathname.match(/^\/words\/(\d+)\/edit$/);
  if (wordEditMatch) {
    return pageUrl.word(wordEditMatch[1]);
  }
  // Terms list: both /words and the legacy /words/edit render the same Alpine
  // SPA. Carry the query through (the list reads `lang` from it).
  if (pathname === '/words' || pathname === '/words/edit') {
    return pageUrl.words(query);
  }
  // Tag management: the server splits term tags (/tags, /tags/term) and text
  // tags (/tags/text) across two pages; the bundle shows both on one tags.html.
  // (The /tags/{term,text}/{id} mutation paths are API calls, not navigation, so
  // they never reach here.)
  if (pathname === '/tags' || pathname === '/tags/term' || pathname === '/tags/text') {
    return pageUrl.tags();
  }
  // Feeds — the first server-enhanced (Job B) surface. The languages page links
  // to /feeds?filterlang=… and the server serves the SPA at /feeds and
  // /feeds/manage; the bundle shows both on feeds.html, gated to a connected
  // server. The query (filterlang, …) is dropped — the SPA has its own filter UI.
  // The wizard (/feeds/new) and per-feed edit routes are not bundled, so they
  // fall through to the connected server's web UI (only reachable when connected).
  if (pathname === '/feeds' || pathname === '/feeds/manage') {
    return pageUrl.feeds();
  }
  // Local dictionaries — server-enhanced (Job B). Reached from /dictionaries?lang=
  // and the per-language /languages/{id}/dictionaries; the bundle shows the
  // management + curated-import page (gated to a connected server). The exact
  // matches below deliberately exclude the file-import form (/dictionaries/import,
  // /languages/{id}/dictionaries/import), which is not bundled and falls through to
  // the connected server's native multipart upload.
  if (pathname === '/dictionaries') {
    const lang = new URLSearchParams(query).get('lang');
    return pageUrl.dictionaries(lang ?? undefined);
  }
  const dictLangMatch = pathname.match(/^\/languages\/(\d+)\/dictionaries$/);
  if (dictLangMatch) {
    return pageUrl.dictionaries(dictLangMatch[1]);
  }
  // Preferences: the navbar's "Preferences" link targets the server's
  // /profile/preferences form; route it to the bundled settings page so it
  // resolves locally instead of falling through to a remote server.
  if (pathname === '/profile/preferences') {
    return pageUrl.settings();
  }
  // Plain print, reached from the reader's and library's printer links
  // (/text/{id}/print-plain). The bundled page is plain-print only; the
  // annotated "Improved Annotated Text" (/text/{id}/print) is a server-only
  // feature, so that path is left to fall through to the remote server.
  const printMatch = pathname.match(/^\/text\/(\d+)\/print-plain$/);
  if (printMatch) {
    return pageUrl.print(printMatch[1]);
  }
  // /text/{id}/read
  const readMatch = pathname.match(/^\/text\/(\d+)\/read$/);
  if (readMatch) {
    const params = new URLSearchParams(query);
    return pageUrl.read(readMatch[1], params.get('lang') ?? undefined);
  }
  // /review[?text=|lang=|selection=] — carry the selection params through.
  if (pathname === '/review') {
    const params = new URLSearchParams();
    for (const key of ['text', 'lang', 'selection']) {
      const value = new URLSearchParams(query).get(key);
      if (value) params.set(key, value);
    }
    return pageUrl.review(params);
  }
  return null;
}

/** True for links that should never be intercepted (external, new-tab, etc.). */
function isExternalLink(anchor: HTMLAnchorElement, href: string): boolean {
  if (anchor.target && anchor.target !== '' && anchor.target !== '_self') {
    return true;
  }
  if (anchor.hasAttribute('download')) {
    return true;
  }
  // Absolute URL to somewhere other than our own (bundle) origin.
  if (/^[a-z]+:\/\//i.test(href) || href.startsWith('//')) {
    try {
      return new URL(href, window.location.href).origin !== window.location.origin;
    } catch {
      return true;
    }
  }
  if (href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
    return true;
  }
  return false;
}

/** Resolve an absolute href to a server-relative path ("/text/1/read?x=y"). */
function toServerPath(href: string): string {
  try {
    const u = new URL(href, window.location.href);
    return u.pathname + u.search + u.hash;
  } catch {
    return href;
  }
}

/**
 * Install a single delegated click handler that rewrites in-app navigation.
 * Idempotent: safe to call once per page.
 */
export function installLinkRouter(): void {
  document.addEventListener(
    'click',
    (event) => {
      if (event.defaultPrevented || event.button !== 0) return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

      const anchor = (event.target as Element | null)?.closest('a');
      if (!anchor) return;

      const href = anchor.getAttribute('href');
      if (href === null || href === '' || isExternalLink(anchor, href)) return;

      const path = toServerPath(href);
      const bundled = bundledPageFor(path);

      if (bundled) {
        event.preventDefault();
        window.location.assign(bundled);
        return;
      }

      // Not bundled: hand off to the remote server's web UI if one is known.
      const server = getApiServer();
      if (server && path.startsWith('/')) {
        event.preventDefault();
        window.location.assign(server.replace(/\/+$/, '') + path);
      }
      // No server configured: leave the link to default behavior.
    },
    // Capture so we run before component handlers on plain anchors.
    true
  );
}
