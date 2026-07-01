import { defineConfig, type PluginOption } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import { resolve } from 'path';
import { fileURLToPath } from 'url';
import { cpSync, mkdirSync } from 'fs';

const __dirname = fileURLToPath(new URL('.', import.meta.url));

/**
 * Bundled-client build.
 *
 * Emits standalone HTML pages — connect, library, reader — that boot the
 * frontend bundle (`src/frontend/js/main.ts`) against an on-device DB (and,
 * optionally, a remote `/api/v1`) with no PHP server in the loop, for packaging
 * into the Lukaisu Capacitor/F-Droid app (../lukaisu).
 *
 * The page bodies under `src/frontend/app/` are **static HTML** — they were
 * originally prerendered from the server's PHP views, but that build-time
 * coupling has been severed (the prerendered output is now committed as the
 * source). The pages no longer track the PHP templates; the app owns them. See
 * git history / `lukaisu/BRIEFING.md` for the local-first migration plan.
 *
 * Output: dist-app/ (consumed by ../lukaisu as its Capacitor webDir).
 *
 * Run: `npm run build:app` (from lukaisu-server/).
 */

/**
 * Copy the review feedback sounds into dist-app/sounds so the static
 * `<audio>` sources (`./sounds/*.mp3`) resolve in the bundle.
 */
function copyReviewSounds(): PluginOption {
  return {
    name: 'lukaisu-copy-review-sounds',
    apply: 'build',
    closeBundle() {
      const dest = resolve(__dirname, 'dist-app/sounds');
      mkdirSync(dest, { recursive: true });
      for (const file of ['success.mp3', 'failure.mp3']) {
        cpSync(resolve(__dirname, 'assets/sounds', file), resolve(dest, file));
      }
    }
  };
}

export default defineConfig({
  root: resolve(__dirname, 'src/frontend/app'),
  // Relative asset URLs so pages work when served from the bundle root inside
  // the WebView (capacitor://localhost / https://localhost).
  base: './',
  publicDir: false,

  resolve: {
    alias: {
      '@': resolve(__dirname, 'src/frontend/js'),
      '@shared': resolve(__dirname, 'src/frontend/js/shared'),
      '@modules': resolve(__dirname, 'src/frontend/js/modules'),
      '@css': resolve(__dirname, 'src/frontend/css')
      // NB: no `alpinejs` alias here (unlike vite.config.ts). The packaged
      // client entry (`client.ts`) is Alpine-free; omitting the alias makes any
      // accidental `import 'alpinejs'` into the client graph fail to resolve at
      // build time (plain `alpinejs` isn't a dependency — only `@alpinejs/csp`),
      // so an Alpine leak into the client is caught by the build, not shipped.
    }
  },

  // Svelte 5 is the rendering framework the client is migrating to (from
  // Alpine; the two coexist per-page during the incremental port). Svelte
  // compiles templates to plain JS at build time — no runtime `eval`/`new
  // Function` — so islands run under the bundle's strict `script-src 'self'`
  // CSP with none of Alpine's `@alpinejs/csp` constraints. Preprocess config
  // (TS support) lives in svelte.config.js, shared with `svelte-check`.
  plugins: [svelte(), copyReviewSounds()],

  build: {
    outDir: resolve(__dirname, 'dist-app'),
    emptyOutDir: true,
    target: 'es2020',
    rollupOptions: {
      input: {
        index: resolve(__dirname, 'src/frontend/app/index.html'),
        login: resolve(__dirname, 'src/frontend/app/login.html'),
        register: resolve(__dirname, 'src/frontend/app/register.html'),
        'forgot-password': resolve(__dirname, 'src/frontend/app/forgot-password.html'),
        'reset-password': resolve(__dirname, 'src/frontend/app/reset-password.html'),
        'recover-password': resolve(__dirname, 'src/frontend/app/recover-password.html'),
        library: resolve(__dirname, 'src/frontend/app/library.html'),
        read: resolve(__dirname, 'src/frontend/app/read.html'),
        review: resolve(__dirname, 'src/frontend/app/review.html'),
        language: resolve(__dirname, 'src/frontend/app/language.html'),
        text: resolve(__dirname, 'src/frontend/app/text.html'),
        words: resolve(__dirname, 'src/frontend/app/words.html'),
        word: resolve(__dirname, 'src/frontend/app/word.html'),
        languages: resolve(__dirname, 'src/frontend/app/languages.html'),
        'language-edit': resolve(__dirname, 'src/frontend/app/language-edit.html'),
        'starter-vocab': resolve(__dirname, 'src/frontend/app/starter-vocab.html'),
        'bulk-translate': resolve(__dirname, 'src/frontend/app/bulk-translate.html'),
        'word-upload': resolve(__dirname, 'src/frontend/app/word-upload.html'),
        'text-edit': resolve(__dirname, 'src/frontend/app/text-edit.html'),
        'text-check': resolve(__dirname, 'src/frontend/app/text-check.html'),
        tags: resolve(__dirname, 'src/frontend/app/tags.html'),
        'tag-form': resolve(__dirname, 'src/frontend/app/tag-form.html'),
        feeds: resolve(__dirname, 'src/frontend/app/feeds.html'),
        'feed-form': resolve(__dirname, 'src/frontend/app/feed-form.html'),
        books: resolve(__dirname, 'src/frontend/app/books.html'),
        book: resolve(__dirname, 'src/frontend/app/book.html'),
        statistics: resolve(__dirname, 'src/frontend/app/statistics.html'),
        dictionaries: resolve(__dirname, 'src/frontend/app/dictionaries.html'),
        'dictionary-import': resolve(__dirname, 'src/frontend/app/dictionary-import.html'),
        texts: resolve(__dirname, 'src/frontend/app/texts.html'),
        settings: resolve(__dirname, 'src/frontend/app/settings.html'),
        'text-print': resolve(__dirname, 'src/frontend/app/text-print.html'),
        home: resolve(__dirname, 'src/frontend/app/home.html')
      }
    }
  }
});
