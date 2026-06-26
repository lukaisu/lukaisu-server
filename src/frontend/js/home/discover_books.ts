/**
 * "Discover books" disclosure for the bundled home dashboard.
 *
 * The Gutenberg/GDL suggestion rows reach external catalogs (CORS-free in the
 * app). We mount them behind an explicit toggle rather than auto-loading on
 * every home open, so a passive home visit makes **no** outbound request — the
 * offline-first dashboard stays inert until the user asks to discover books.
 * The nested `gutenbergSuggestions` / `gdlSuggestions` components only
 * initialize (and fetch) once this opens, via `x-if`.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import Alpine from 'alpinejs';
import { initIcons } from '@shared/icons/lucide_icons';

interface DiscoverBooksData {
  open: boolean;
  toggle(): void;
}

export function discoverBooksData(): DiscoverBooksData {
  return {
    open: false,
    toggle() {
      this.open = !this.open;
      if (this.open) {
        // Icons inside the just-revealed rows need a (re)scan.
        requestAnimationFrame(() => initIcons());
      }
    },
  };
}

export function initDiscoverBooks(): void {
  Alpine.data('discoverBooks', discoverBooksData);
}

initDiscoverBooks();
