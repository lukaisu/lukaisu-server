/**
 * Stylesheet bundle for the last server-rendered pages.
 *
 * The entire browser UI is a connected client (mobile app, or any /api/v1
 * consumer) — the server itself renders no reading/learning UI at all
 * (R6d-R6f, the headless cut). The only HTML the server still renders is the
 * OAuth account-link-confirm form (google/microsoft_link_confirm.php), shown
 * mid-login when an email already has an account. Those pages ship NO
 * JavaScript — they render server-side with Bulma + a frozen copy of the base
 * styles and use only vanilla markup.
 *
 * This entry therefore imports CSS only; it replaces the Alpine-ful `main.ts`
 * server entry, which was deleted (R6d). `PageLayoutHelper::renderPageStart`
 * loads it synchronously (`ViteHelper::assets('js/styles.ts', false)`) so the
 * styles apply without any JS to flip a `media="print"` link.
 *
 * `styles.css` is imported from `assets/css/`, not the app's own
 * `webapp/css/base/` (in the sibling `lukaisu` repo, Phase M) — a frozen
 * snapshot kept here since these 2 static pages don't track the app's
 * evolving design system. `html5_audio_player.css` / `icons.css` aren't
 * needed: no audio player here, and icon `<i data-lucide>` tags don't render
 * anyway (no JS to init them).
 */

// Bulma CSS framework
import 'bulma/css/bulma.min.css';

// Frozen snapshot of the base theme (see docblock above).
import '../assets/css/styles.css';
