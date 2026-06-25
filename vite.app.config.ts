import { defineConfig, type PluginOption } from 'vite';
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
      '@css': resolve(__dirname, 'src/frontend/css'),
      // CSP-compliant Alpine build (matches vite.config.ts; the CSP eval
      // restriction is enforced the same way in the bundled client).
      'alpinejs': '@alpinejs/csp'
    }
  },

  plugins: [copyReviewSounds()],

  build: {
    outDir: resolve(__dirname, 'dist-app'),
    emptyOutDir: true,
    target: 'es2020',
    rollupOptions: {
      input: {
        index: resolve(__dirname, 'src/frontend/app/index.html'),
        library: resolve(__dirname, 'src/frontend/app/library.html'),
        read: resolve(__dirname, 'src/frontend/app/read.html'),
        review: resolve(__dirname, 'src/frontend/app/review.html'),
        language: resolve(__dirname, 'src/frontend/app/language.html'),
        text: resolve(__dirname, 'src/frontend/app/text.html'),
        words: resolve(__dirname, 'src/frontend/app/words.html'),
        word: resolve(__dirname, 'src/frontend/app/word.html')
      }
    }
  }
});
