/**
 * Navbar Streak Component - Shows current streak as a flame icon in the navbar.
 *
 * Lightweight component that fetches only the streak endpoint.
 *
 * @license Unlicense <http://unlicense.org/>
 * @since   3.0.0
 */

import Alpine from 'alpinejs';
import { routeLocal } from '@shared/offline/local/router';

interface NavbarStreakState {
  streak: number;
  init(): void;
}

function navbarStreak(): NavbarStreakState {
  return {
    streak: 0,

    init() {
      // Local-first mode computes the streak from the on-device DB (no server);
      // otherwise fall back to the original network fetch.
      void (async () => {
        const local = await routeLocal('GET', '/activity/streak', undefined);
        if (local.handled) {
          if (!local.error && local.data && typeof local.data === 'object') {
            this.streak = (local.data as { current_streak?: number }).current_streak ?? 0;
          }
          return;
        }
        try {
          const response = await fetch('/api/v1/activity/streak');
          const data = (await response.json()) as { current_streak?: number };
          this.streak = data.current_streak ?? 0;
        } catch {
          // Silently ignore — streak is non-critical.
        }
      })();
    },
  };
}

Alpine.data('navbarStreak', navbarStreak);
