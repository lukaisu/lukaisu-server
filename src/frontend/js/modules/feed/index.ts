/**
 * Feed Module Entry Point.
 *
 * Registers all Alpine.js components and stores for the feed feature.
 * Import this file to initialize the full feed functionality.
 *
 * @license Unlicense <http://unlicense.org/>
 */

// Types (for re-export)
export * from './types/feed_wizard_types';

// API (side-effect import — has overlapping type names with feed_wizard_types)
import './api/feeds_api';

// Utilities
export * from './utils/xpath_utils';

// Services
export { HighlightService, getHighlightService, initHighlightService } from './services/highlight_service';

// Stores
export { initFeedWizardStore, getFeedWizardStore } from './stores/feed_wizard_store';

// Shared utilities needed by feed pages
import '@shared/components/sorttable';
import '@shared/forms/bulk_actions';

// Components (side effects — register Alpine.data)
import './components/feed_form_component';
import './components/feed_multi_load_component';
import './components/feed_loader_component';
import './components/feed_index_component';
import './components/feed_browse_component';
import './components/feed_text_edit_component';
export { feedWizardStep1Data, initFeedWizardStep1Alpine } from './components/feed_wizard_step1';
export { feedWizardStep2Data, initFeedWizardStep2Alpine } from './components/feed_wizard_step2';
export { feedWizardStep3Data, initFeedWizardStep3Alpine } from './components/feed_wizard_step3';
export { feedWizardStep4Data, initFeedWizardStep4Alpine } from './components/feed_wizard_step4';

// The feed-manager SPA (Alpine `feed_manager_app.ts` / `feed_manager_store.ts`
// + the `spa.php` view at /feeds/manage) was retired: that surface is now the
// Svelte `FeedsPage` island on the bundle's feeds.html, and /feeds + /feeds/manage
// 302 to it. The remaining components above still drive the PHP feed pages
// (/feeds, /feeds/edit, /feeds/new, wizard) that are not yet migrated.
