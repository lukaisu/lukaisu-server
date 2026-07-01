/**
 * Feed Module Entry Point.
 *
 * The feed surfaces are now Svelte islands: the feed-manager SPA is the
 * `FeedsPage` island (feeds.html; /feeds + /feeds/manage 302 to it) and the
 * new/edit *form* is the `FeedFormPage` island (/feeds/new + /feeds/{id}/edit
 * 302 to it). Both talk to the existing `/api/v1/feeds*` API via `feeds_api`.
 *
 * The old Alpine feed cluster — the 4-step visual wizard, the browse/index/edit
 * pages, the feed-load progress page and multi-load — was deleted; nothing here
 * registers Alpine components anymore. `feeds_api` is re-imported for its side
 * effects so this chunk stays the feed module's data-layer anchor.
 *
 * @license Unlicense <http://unlicense.org/>
 */

import './api/feeds_api';
