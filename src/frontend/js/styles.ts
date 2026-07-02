/**
 * Stylesheet bundle for the last server-rendered pages.
 *
 * Under the headless cut (Phase R) the entire browser UI is a bundled Svelte
 * client (served from dist-app via BundleController) or a redirected page. The
 * only HTML the server still renders itself is the OAuth account-link-confirm
 * form (google/microsoft_link_confirm.php), shown mid-login when an email
 * already has an account. Those pages ship NO JavaScript — they render
 * server-side with Bulma + the base styles and use only vanilla markup.
 *
 * This entry therefore imports CSS only; it replaces the Alpine-ful `main.ts`
 * server entry, which was deleted (R6d). `PageLayoutHelper::renderPageStart`
 * loads it synchronously (`ViteHelper::assets('js/styles.ts', false)`) so the
 * styles apply without any JS to flip a `media="print"` link.
 */

// Bulma CSS framework
import 'bulma/css/bulma.min.css';

// Base styles
import '../css/base/styles.css';
import '../css/base/html5_audio_player.css';
import '../css/base/icons.css';
