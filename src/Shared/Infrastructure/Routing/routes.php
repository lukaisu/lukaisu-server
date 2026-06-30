<?php

/**
 * Lukaisu Server Route Configuration
 *
 * This file defines all routes for the application.
 * Routes map URL paths to controller methods.
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Shared\Infrastructure\Routing
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Shared\Infrastructure\Routing;

use Lukaisu\Shared\Infrastructure\Routing\Middleware\AuthMiddleware;
use Lukaisu\Shared\Infrastructure\Routing\Middleware\AuthRateLimitMiddleware;
use Lukaisu\Shared\Infrastructure\Routing\Middleware\AdminMiddleware;
use Lukaisu\Shared\Infrastructure\Routing\Middleware\CsrfMiddleware;
use Lukaisu\Shared\Infrastructure\Routing\Middleware\RateLimitMiddleware;

/**
 * Auth middleware for protected routes.
 * Includes CSRF protection for state-changing requests (POST, PUT, DELETE).
 *
 * @var array<string>
 */
const AUTH_MIDDLEWARE = [AuthMiddleware::class, CsrfMiddleware::class];

/**
 * Admin middleware for admin-only routes.
 * Requires authentication AND admin role, plus CSRF protection.
 *
 * @var array<string>
 */
const ADMIN_MIDDLEWARE = [AdminMiddleware::class, CsrfMiddleware::class];

/**
 * Register all application routes.
 *
 * Routes are organized into:
 * - Public routes: No authentication required (login, register, etc.)
 * - Protected routes: Require user authentication
 *
 * @param Router $router The router instance to register routes with
 *
 * @return void
 */
function registerRoutes(Router $router): void
{
    // ==================== BUNDLED CLIENT (PROTECTED) ====================

    // The reading/learning UI is served from dist-app/ (built by
    // `npm run build:app`) under /app/. HTML pages flow through BundleController
    // so it can inject a fresh CSRF token + same-origin runtime config; the
    // bundle's static assets are served by Router::resolveStaticAsset
    // (/app/* -> dist-app/*). AuthMiddleware gates the pages exactly like the
    // PHP views did (single-user passes through; multi-user → /login). The
    // legacy Job-A page routes below redirect into these bundle URLs.
    $router->registerPrefixWithMiddleware(
        '/app',
        'Lukaisu\\Shared\\Http\\BundleController@serve',
        AUTH_MIDDLEWARE
    );

    // ==================== TEXT ROUTES (PROTECTED) ====================

    // Read text (Alpine.js - client-side rendering)
    // New RESTful route: /text/123/read
    $router->get('/text/{text:int}/read', 'Lukaisu\\Modules\\Text\\Http\\TextController@read', AUTH_MIDDLEWARE);
    // Legacy route: /text/read?text=123
    $router->get('/text/read', 'Lukaisu\\Modules\\Text\\Http\\TextController@read', AUTH_MIDDLEWARE);

    // New text form (RESTful route)
    $router->get('/texts/new', 'Lukaisu\\Modules\\Text\\Http\\TextController@new', AUTH_MIDDLEWARE);
    $router->post('/texts/new', 'Lukaisu\\Modules\\Text\\Http\\TextController@new', AUTH_MIDDLEWARE);

    // Edit text form (RESTful route): /texts/123/edit
    $router->get('/texts/{id:int}/edit', 'Lukaisu\\Modules\\Text\\Http\\TextController@editSingle', AUTH_MIDDLEWARE);
    $router->post('/texts/{id:int}/edit', 'Lukaisu\\Modules\\Text\\Http\\TextController@editSingle', AUTH_MIDDLEWARE);

    // Delete text (RESTful route): DELETE /texts/123
    $router->delete('/texts/{id:int}', 'Lukaisu\\Modules\\Text\\Http\\TextController@delete', AUTH_MIDDLEWARE);

    // Archive text (RESTful route): POST /texts/123/archive
    $router->post('/texts/{id:int}/archive', 'Lukaisu\\Modules\\Text\\Http\\TextController@archive', AUTH_MIDDLEWARE);

    // Unarchive text (RESTful route): POST /texts/123/unarchive
    $router->post('/texts/{id:int}/unarchive', 'Lukaisu\\Modules\\Text\\Http\\TextController@unarchive', AUTH_MIDDLEWARE);

    // Texts list and legacy edit routes
    $router->registerWithMiddleware('/text/edit', 'Lukaisu\\Modules\\Text\\Http\\TextController@edit', AUTH_MIDDLEWARE);
    $router->registerWithMiddleware('/texts', 'Lukaisu\\Modules\\Text\\Http\\TextController@edit', AUTH_MIDDLEWARE);

    // Display improved text
    // New RESTful route: /text/123/display
    $router->get('/text/{text:int}/display', 'Lukaisu\\Modules\\Text\\Http\\TextController@display', AUTH_MIDDLEWARE);
    // Legacy route: /text/display?text=123
    $router->get('/text/display', 'Lukaisu\\Modules\\Text\\Http\\TextController@display', AUTH_MIDDLEWARE);

    // Print text (TextPrintController from Text module)
    // RESTful route: /text/123/print
    $router->get(
        '/text/{text:int}/print',
        'Lukaisu\\Modules\\Text\\Http\\TextPrintController@printAnnotated',
        AUTH_MIDDLEWARE
    );
    // RESTful route: /text/123/print/edit
    $router->get(
        '/text/{text:int}/print/edit',
        'Lukaisu\\Modules\\Text\\Http\\TextPrintController@editAnnotation',
        AUTH_MIDDLEWARE
    );
    // RESTful route: DELETE /text/123/annotation
    $router->delete(
        '/text/{text:int}/annotation',
        'Lukaisu\\Modules\\Text\\Http\\TextPrintController@deleteAnnotation',
        AUTH_MIDDLEWARE
    );
    // RESTful route: /text/123/print-plain
    $router->get(
        '/text/{text:int}/print-plain',
        'Lukaisu\\Modules\\Text\\Http\\TextPrintController@printPlain',
        AUTH_MIDDLEWARE
    );
    // Legacy route: /text/print-plain?text=123
    $router->registerWithMiddleware(
        '/text/print-plain',
        'Lukaisu\\Modules\\Text\\Http\\TextPrintController@printPlain',
        AUTH_MIDDLEWARE
    );

    // Check text
    $router->registerWithMiddleware('/text/check', 'Lukaisu\\Modules\\Text\\Http\\TextController@check', AUTH_MIDDLEWARE);

    // Archived texts
    $router->registerWithMiddleware(
        '/text/archived',
        'Lukaisu\\Modules\\Text\\Http\\TextController@archived',
        AUTH_MIDDLEWARE
    );

    // Edit archived text (RESTful route): /text/archived/123/edit
    $router->get(
        '/text/archived/{id:int}/edit',
        'Lukaisu\\Modules\\Text\\Http\\TextController@archivedEdit',
        AUTH_MIDDLEWARE
    );
    $router->post(
        '/text/archived/{id:int}/edit',
        'Lukaisu\\Modules\\Text\\Http\\TextController@archivedEdit',
        AUTH_MIDDLEWARE
    );

    // Delete archived text (RESTful route): DELETE /text/archived/123
    $router->delete(
        '/text/archived/{id:int}',
        'Lukaisu\\Modules\\Text\\Http\\TextController@deleteArchived',
        AUTH_MIDDLEWARE
    );

    // ==================== WORD/TERM ROUTES (PROTECTED) ====================
    // Split into focused controllers: TermEditController, TermDisplayController,
    // TermStatusController, TermApiController, TermImportController

    // Edit word (TermEditController)
    $router->registerWithMiddleware(
        '/word/edit',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermEditController@editWord',
        AUTH_MIDDLEWARE
    );

    // Edit term while testing (TermEditController)
    $router->registerWithMiddleware(
        '/word/edit-term',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermEditController@editTerm',
        AUTH_MIDDLEWARE
    );

    // Edit single word form (RESTful route): /words/123/edit
    $router->get(
        '/words/{id:int}/edit',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermEditController@editWordById',
        AUTH_MIDDLEWARE
    );
    $router->post(
        '/words/{id:int}/edit',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermEditController@editWordById',
        AUTH_MIDDLEWARE
    );

    // Delete word (RESTful route): DELETE /words/123
    $router->delete(
        '/words/{id:int}',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermEditController@deleteWord',
        AUTH_MIDDLEWARE
    );

    // Words list - Alpine.js SPA version (TermDisplayController)
    $router->registerWithMiddleware(
        '/words/edit',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermDisplayController@listEditAlpine',
        AUTH_MIDDLEWARE
    );

    // Multi-word create/edit is served by the bundled client's multi-word modal
    // via TermsApi.createMultiWord/updateMultiWord (/api/v1); the legacy
    // /word/edit-multi web form (MultiWordController) was retired.

    // All words (list view) - Alpine.js SPA version (TermDisplayController)
    $router->registerWithMiddleware(
        '/words',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermDisplayController@listEditAlpine',
        AUTH_MIDDLEWARE
    );

    // New word (TermEditController)
    // RESTful route: /words/new
    $router->get('/words/new', 'Lukaisu\\Modules\\Vocabulary\\Http\\TermEditController@createWord', AUTH_MIDDLEWARE);
    // Legacy route: /word/new
    $router->registerWithMiddleware(
        '/word/new',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermEditController@createWord',
        AUTH_MIDDLEWARE
    );

    // Show word - new RESTful route with typed parameter (TermDisplayController)
    $router->get('/word/{wid:int}', 'Lukaisu\\Modules\\Vocabulary\\Http\\TermDisplayController@showWord', AUTH_MIDDLEWARE);
    // Legacy route for backward compatibility
    $router->get('/word/show', 'Lukaisu\\Modules\\Vocabulary\\Http\\TermDisplayController@showWord', AUTH_MIDDLEWARE);

    // Inline edit (TermEditController)
    $router->registerWithMiddleware(
        '/word/inline-edit',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermEditController@inlineEdit',
        AUTH_MIDDLEWARE
    );

    // Bulk translate. The GET page is served by the bundled client (Svelte
    // BulkTranslate island); see the /app redirects below. The form POST (saving
    // the chosen terms) keeps this controller — the bundle island posts to it.
    $router->registerWithMiddleware(
        '/word/bulk-translate',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermImportController@bulkTranslate',
        AUTH_MIDDLEWARE
    );
    // JSON bootstrap config for the island (dictionaries + page of unknown words).
    $router->get(
        '/word/bulk-translate/config',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermImportController@config',
        AUTH_MIDDLEWARE
    );

    // Term creation from the reader is served by the bundled client via
    // /api/v1/terms (createJson); the legacy /vocabulary/term-hover web route
    // (TermDisplayController::hoverCreate) was retired.

    // Similar terms lookup (TermDisplayController)
    $router->registerWithMiddleware(
        '/vocabulary/similar-terms',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermDisplayController@similarTerms',
        AUTH_MIDDLEWARE
    );

    // Vocabulary JSON API routes (TermApiController)
    $router->registerWithMiddleware(
        '/vocabulary/term',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermApiController@getTermJson',
        AUTH_MIDDLEWARE,
        'GET'
    );
    $router->registerWithMiddleware(
        '/vocabulary/term',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermApiController@createJson',
        AUTH_MIDDLEWARE,
        'POST'
    );
    $router->registerWithMiddleware(
        '/vocabulary/term',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermApiController@updateJson',
        AUTH_MIDDLEWARE,
        'PUT'
    );
    // Update term status (TermStatusController)
    // New RESTful route: PUT /vocabulary/term/123/status
    $router->put(
        '/vocabulary/term/{wid:int}/status',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermStatusController@updateStatus',
        AUTH_MIDDLEWARE
    );
    // Legacy route: PUT /vocabulary/term/status?wid=123
    $router->put(
        '/vocabulary/term/status',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermStatusController@updateStatus',
        AUTH_MIDDLEWARE
    );
    // Delete term (TermApiController)
    $router->registerWithMiddleware(
        '/vocabulary/term/delete',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermApiController@delete',
        AUTH_MIDDLEWARE,
        'POST'
    );

    // Set all words status (wellknown/ignore) (TermStatusController)
    $router->registerWithMiddleware(
        '/word/set-all-status',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermStatusController@markAllWords',
        AUTH_MIDDLEWARE
    );

    // Upload words (TermImportController). The GET page is served by the bundled
    // client (Svelte WordUpload island); see the /app redirects below. The file
    // POST (importing a term/dictionary file) keeps this controller — the bundle
    // island posts to it natively (multipart), and the browser navigates to its
    // server-rendered result (upload_result.php / dict-import form re-render).
    $router->registerWithMiddleware(
        '/word/upload',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermImportController@upload',
        AUTH_MIDDLEWARE
    );
    // JSON bootstrap config for the island (current language, FrequencyWords
    // availability, curated dictionaries, the upload/import endpoints).
    $router->get(
        '/word/upload/config',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermImportController@uploadConfig',
        AUTH_MIDDLEWARE
    );

    // Review status change during review (iframe/ajax view).
    // NB: the dead text-reader frame routes (set-status, delete-term,
    // delete-multi, insert-wellknown, insert-ignore) are unregistered here —
    // the modern reader changes status via /api/v1/terms/* and never linked to
    // them. Their now-unreachable controller methods + result views are retained
    // pending the frontend dead-popup cleanup (needs in-browser E2E to verify).
    $router->registerWithMiddleware(
        '/word/set-review-status',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermStatusController@setReviewStatusView',
        AUTH_MIDDLEWARE
    );

    // ==================== LANGUAGE ROUTES (PROTECTED) ====================

    // Language create / edit / delete / refresh are served by the bundled client
    // through the /api/v1/languages REST API (LanguageApiHandler); the legacy web
    // form routes (LanguageController) were retired. GET /languages, /languages/new
    // and /languages/{id}/edit are redirected to the bundle (see /app redirects).

    // Starter vocabulary (shown after language creation). The GET page route is
    // served by the bundled client (Svelte StarterVocab island); see the /app
    // redirects below. This JSON config route feeds that island the server-only
    // bits (language name, FrequencyWords availability, curated dictionaries).
    $router->get(
        '/languages/{id:int}/starter-vocab/config',
        'Lukaisu\\Modules\\Vocabulary\\Http\\StarterVocabController@config',
        AUTH_MIDDLEWARE
    );
    $router->post(
        '/languages/{id:int}/starter-vocab/import',
        'Lukaisu\\Modules\\Vocabulary\\Http\\StarterVocabController@import',
        AUTH_MIDDLEWARE
    );
    $router->post(
        '/languages/{id:int}/starter-vocab/enrich',
        'Lukaisu\\Modules\\Vocabulary\\Http\\StarterVocabController@enrich',
        AUTH_MIDDLEWARE
    );
    $router->get(
        '/languages/{id:int}/starter-vocab/skip',
        'Lukaisu\\Modules\\Vocabulary\\Http\\StarterVocabController@skip',
        AUTH_MIDDLEWARE
    );

    // ==================== TAG ROUTES (PROTECTED) ====================

    // Term tags (Tags module)
    // The controller's new() handles both GET (show form) and POST (save).
    $router->get('/tags/new', 'Lukaisu\\Modules\\Tags\\Http\\TermTagController@new', AUTH_MIDDLEWARE);
    $router->post('/tags/new', 'Lukaisu\\Modules\\Tags\\Http\\TermTagController@new', AUTH_MIDDLEWARE);

    // Edit term tag (RESTful route): GET/POST /tags/123/edit
    $router->get('/tags/{id:int}/edit', 'Lukaisu\\Modules\\Tags\\Http\\TermTagController@edit', AUTH_MIDDLEWARE);
    $router->post('/tags/{id:int}/edit', 'Lukaisu\\Modules\\Tags\\Http\\TermTagController@edit', AUTH_MIDDLEWARE);

    // Delete term tag (RESTful route): DELETE /tags/123
    $router->delete('/tags/{id:int}', 'Lukaisu\\Modules\\Tags\\Http\\TermTagController@delete', AUTH_MIDDLEWARE);

    // GET /tags (term-tag list) is served by the bundled client; see the /app redirects below.

    // Text tags (Tags module)
    // Same pattern as term tags: controller's new() handles GET and POST.
    $router->get('/tags/text/new', 'Lukaisu\\Modules\\Tags\\Http\\TextTagController@new', AUTH_MIDDLEWARE);
    $router->post('/tags/text/new', 'Lukaisu\\Modules\\Tags\\Http\\TextTagController@new', AUTH_MIDDLEWARE);

    // Edit text tag (RESTful route): GET/POST /tags/text/123/edit
    $router->get('/tags/text/{id:int}/edit', 'Lukaisu\\Modules\\Tags\\Http\\TextTagController@edit', AUTH_MIDDLEWARE);
    $router->post('/tags/text/{id:int}/edit', 'Lukaisu\\Modules\\Tags\\Http\\TextTagController@edit', AUTH_MIDDLEWARE);

    // Delete text tag (RESTful route): DELETE /tags/text/123
    $router->delete('/tags/text/{id:int}', 'Lukaisu\\Modules\\Tags\\Http\\TextTagController@delete', AUTH_MIDDLEWARE);

    // GET /tags/text (text-tag list) is served by the bundled client; see the /app redirects below.

    // ==================== FEED ROUTES (PROTECTED) ====================

    // GET /feeds and GET /feeds/manage (the feed-manager SPA) are served by the
    // bundled client (Svelte FeedsPage); see the /app redirects below. The old
    // Alpine `spa.php` view + FeedController@spa handler were retired. The
    // non-GET /feeds handler (marked-items text creation) is kept below.

    // New feed form (RESTful route)
    $router->get('/feeds/new', 'Lukaisu\\Modules\\Feed\\Http\\FeedController@newFeed', AUTH_MIDDLEWARE);
    $router->post('/feeds/new', 'Lukaisu\\Modules\\Feed\\Http\\FeedController@newFeed', AUTH_MIDDLEWARE);

    // Edit feed form (RESTful route): /feeds/123/edit
    $router->get('/feeds/{id:int}/edit', 'Lukaisu\\Modules\\Feed\\Http\\FeedController@editFeed', AUTH_MIDDLEWARE);
    $router->post('/feeds/{id:int}/edit', 'Lukaisu\\Modules\\Feed\\Http\\FeedController@editFeed', AUTH_MIDDLEWARE);

    // Delete feed (RESTful route): DELETE /feeds/123
    $router->delete('/feeds/{id:int}', 'Lukaisu\\Modules\\Feed\\Http\\FeedController@deleteFeed', AUTH_MIDDLEWARE);

    // Load/refresh feed (RESTful route): POST /feeds/123/load
    $router->get('/feeds/{id:int}/load', 'Lukaisu\\Modules\\Feed\\Http\\FeedController@loadFeedRoute', AUTH_MIDDLEWARE);

    // Multi-load feeds interface (RESTful route)
    $router->get('/feeds/multi-load', 'Lukaisu\\Modules\\Feed\\Http\\FeedController@multiLoad', AUTH_MIDDLEWARE);

    // Feeds list
    $router->registerWithMiddleware('/feeds', 'Lukaisu\\Modules\\Feed\\Http\\FeedController@index', AUTH_MIDDLEWARE);

    // Edit feeds (legacy route - handles query params)
    $router->registerWithMiddleware('/feeds/edit', 'Lukaisu\\Modules\\Feed\\Http\\FeedController@edit', AUTH_MIDDLEWARE);

    // Feed wizard
    $router->registerWithMiddleware(
        '/feeds/wizard',
        'Lukaisu\\Modules\\Feed\\Http\\FeedWizardController@wizard',
        AUTH_MIDDLEWARE
    );

    // ==================== BOOK ROUTES (PROTECTED) ====================
    // Book module routes for EPUB import and book management

    // Books list
    $router->registerWithMiddleware('/books', 'Lukaisu\\Modules\\Book\\Http\\BookController@index', AUTH_MIDDLEWARE);

    // Book detail (chapters list)
    $router->get('/book/{id:int}', 'Lukaisu\\Modules\\Book\\Http\\BookController@show', AUTH_MIDDLEWARE);

    // Import EPUB form and processing
    $router->registerWithMiddleware('/book/import', 'Lukaisu\\Modules\\Book\\Http\\BookController@import', AUTH_MIDDLEWARE);

    // Delete book
    $router->post('/book/{id:int}/delete', 'Lukaisu\\Modules\\Book\\Http\\BookController@delete', AUTH_MIDDLEWARE);

    // ==================== LOCAL DICTIONARY ROUTES (PROTECTED) ====================
    // All dictionary routes use DictionaryController from the Dictionary module

    // RESTful routes: /languages/{id}/dictionaries
    $router->get(
        '/languages/{id:int}/dictionaries',
        'Lukaisu\\Modules\\Dictionary\\Http\\DictionaryController@index',
        AUTH_MIDDLEWARE
    );
    $router->post(
        '/languages/{id:int}/dictionaries',
        'Lukaisu\\Modules\\Dictionary\\Http\\DictionaryController@index',
        AUTH_MIDDLEWARE
    );
    $router->get(
        '/languages/{id:int}/dictionaries/import',
        'Lukaisu\\Modules\\Dictionary\\Http\\DictionaryController@import',
        AUTH_MIDDLEWARE
    );
    $router->post(
        '/languages/{id:int}/dictionaries/import',
        'Lukaisu\\Modules\\Dictionary\\Http\\DictionaryController@processImport',
        AUTH_MIDDLEWARE
    );

    // Legacy routes (with ?lang= query parameter)
    $router->registerWithMiddleware(
        '/dictionaries',
        'Lukaisu\\Modules\\Dictionary\\Http\\DictionaryController@index',
        AUTH_MIDDLEWARE
    );

    // Import wizard
    $router->registerWithMiddleware(
        '/dictionaries/import',
        'Lukaisu\\Modules\\Dictionary\\Http\\DictionaryController@import',
        AUTH_MIDDLEWARE,
        'GET'
    );
    $router->registerWithMiddleware(
        '/dictionaries/import',
        'Lukaisu\\Modules\\Dictionary\\Http\\DictionaryController@processImport',
        AUTH_MIDDLEWARE,
        'POST'
    );

    // Delete dictionary
    $router->registerWithMiddleware(
        '/dictionaries/delete',
        'Lukaisu\\Modules\\Dictionary\\Http\\DictionaryController@delete',
        AUTH_MIDDLEWARE,
        'POST'
    );

    // Preview (AJAX)
    $router->registerWithMiddleware(
        '/dictionaries/preview',
        'Lukaisu\\Modules\\Dictionary\\Http\\DictionaryController@preview',
        AUTH_MIDDLEWARE,
        'POST'
    );

    // ==================== ADMIN ROUTES (ADMIN ONLY) ====================
    // These routes require admin role, not just authentication

    // Admin dashboard
    $router->registerWithMiddleware(
        '/admin',
        'Lukaisu\\Modules\\Admin\\Http\\AdminController@dashboard',
        ADMIN_MIDDLEWARE
    );

    // Backup & Restore (Admin module)
    $router->registerWithMiddleware(
        '/admin/backup',
        'Lukaisu\\Modules\\Admin\\Http\\AdminController@backup',
        ADMIN_MIDDLEWARE
    );

    // Database Wizard (Admin module)
    $router->registerWithMiddleware(
        '/admin/wizard',
        'Lukaisu\\Modules\\Admin\\Http\\AdminController@wizard',
        ADMIN_MIDDLEWARE
    );

    // Statistics (User module) - legacy /admin/statistics redirects to /profile/statistics
    $router->registerWithMiddleware(
        '/admin/statistics',
        'Lukaisu\\Modules\\User\\Http\\StatisticsController@redirectFromAdmin',
        AUTH_MIDDLEWARE
    );

    // Install Demo (Admin module)
    $router->registerWithMiddleware(
        '/admin/install-demo',
        'Lukaisu\\Modules\\Admin\\Http\\AdminController@installDemo',
        ADMIN_MIDDLEWARE
    );

    // Settings (Admin module)
    $router->registerWithMiddleware(
        '/admin/settings',
        'Lukaisu\\Modules\\Admin\\Http\\AdminController@settings',
        ADMIN_MIDDLEWARE
    );

    // Server data (Admin module)
    $router->registerWithMiddleware(
        '/admin/server-data',
        'Lukaisu\\Modules\\Admin\\Http\\AdminController@serverData',
        ADMIN_MIDDLEWARE
    );

    // User Management (Admin module)
    $router->get('/admin/users', 'Lukaisu\\Modules\\Admin\\Http\\UserManagementController@index', ADMIN_MIDDLEWARE);
    $router->post('/admin/users', 'Lukaisu\\Modules\\Admin\\Http\\UserManagementController@index', ADMIN_MIDDLEWARE);
    $router->get('/admin/users/new', 'Lukaisu\\Modules\\Admin\\Http\\UserManagementController@create', ADMIN_MIDDLEWARE);
    $router->post('/admin/users/new', 'Lukaisu\\Modules\\Admin\\Http\\UserManagementController@create', ADMIN_MIDDLEWARE);
    $router->get(
        '/admin/users/{id:int}/edit',
        'Lukaisu\\Modules\\Admin\\Http\\UserManagementController@edit',
        ADMIN_MIDDLEWARE
    );
    $router->post(
        '/admin/users/{id:int}/edit',
        'Lukaisu\\Modules\\Admin\\Http\\UserManagementController@edit',
        ADMIN_MIDDLEWARE
    );
    $router->post(
        '/admin/users/{id:int}/delete',
        'Lukaisu\\Modules\\Admin\\Http\\UserManagementController@delete',
        ADMIN_MIDDLEWARE
    );
    $router->post(
        '/admin/users/{id:int}/activate',
        'Lukaisu\\Modules\\Admin\\Http\\UserManagementController@activate',
        ADMIN_MIDDLEWARE
    );
    $router->post(
        '/admin/users/{id:int}/deactivate',
        'Lukaisu\\Modules\\Admin\\Http\\UserManagementController@deactivate',
        ADMIN_MIDDLEWARE
    );
    $router->post(
        '/admin/users/{id:int}/role',
        'Lukaisu\\Modules\\Admin\\Http\\UserManagementController@setRole',
        ADMIN_MIDDLEWARE
    );

    // ==================== USER PROFILE (AUTH REQUIRED) ====================
    $router->get('/profile', 'Lukaisu\\Modules\\User\\Http\\UserController@profileForm', AUTH_MIDDLEWARE);
    $router->post('/profile', 'Lukaisu\\Modules\\User\\Http\\UserController@updateProfile', AUTH_MIDDLEWARE);
    $router->post('/profile/password', 'Lukaisu\\Modules\\User\\Http\\UserController@changePassword', AUTH_MIDDLEWARE);
    $router->post('/profile/preferences', 'Lukaisu\\Modules\\User\\Http\\UserController@savePreferences', AUTH_MIDDLEWARE);
    $router->get(
        '/profile/statistics',
        'Lukaisu\\Modules\\User\\Http\\StatisticsController@show',
        AUTH_MIDDLEWARE
    );

    // ==================== AUTHENTICATION ROUTES (PUBLIC) ====================
    // All auth routes use UserController from the User module

    // Login - no auth required, rate limited and CSRF-protected on POST.
    // CSRF on a pre-login form blocks login-CSRF: an attacker cannot force a
    // victim to log in as the attacker's account (and then have the victim's
    // reading/vocabulary land there) without first stealing the pre-login
    // session token, which is HttpOnly+SameSite.
    $router->register('/login', 'Lukaisu\\Modules\\User\\Http\\UserController@loginForm', 'GET');
    $router->post(
        '/login',
        'Lukaisu\\Modules\\User\\Http\\UserController@login',
        [AuthRateLimitMiddleware::class, CsrfMiddleware::class]
    );

    // Packaged-client entry: the client-rendered "choose server + log in" flow
    // (token auth; the login POST goes to /api/v1/auth/login) is served by the
    // bundled client — GET /connect 302s to the Svelte ConnectPage (index.html);
    // see the /app redirects below. The old Alpine `client_auth.php` view was retired.

    // Registration - no auth required, rate limited and CSRF-protected on POST.
    $router->register('/register', 'Lukaisu\\Modules\\User\\Http\\UserController@registerForm', 'GET');
    $router->post(
        '/register',
        'Lukaisu\\Modules\\User\\Http\\UserController@register',
        [AuthRateLimitMiddleware::class, CsrfMiddleware::class]
    );
    // One-time recovery code shown after an email-less sign-up or a code reset.
    $router->register(
        '/register/recovery-code',
        'Lukaisu\\Modules\\User\\Http\\UserController@recoveryCodeShown',
        'GET'
    );

    // Logout - POST-only with CSRF so cross-site `<img src=/logout>` cannot
    // log the victim out. The controller handles a missing session gracefully.
    $router->post('/logout', 'Lukaisu\\Modules\\User\\Http\\UserController@logout', [CsrfMiddleware::class]);

    // Email Verification - no auth required for token link.
    $router->register('/verify-email', 'Lukaisu\\Modules\\User\\Http\\UserController@verifyEmail', 'GET');
    // Resend verification is an authenticated state-changing POST (controller
    // gates auth inline). Add CSRF + rate-limit so cross-site forms cannot
    // spam the victim's inbox with verification emails.
    $router->post(
        '/email/resend-verification',
        'Lukaisu\\Modules\\User\\Http\\UserController@resendVerification',
        [AuthRateLimitMiddleware::class, CsrfMiddleware::class]
    );

    // Password Reset - no auth required, rate limited and CSRF-protected on POST.
    $router->register('/password/forgot', 'Lukaisu\\Modules\\User\\Http\\UserController@forgotPasswordForm', 'GET');
    $router->post(
        '/password/forgot',
        'Lukaisu\\Modules\\User\\Http\\UserController@forgotPassword',
        [AuthRateLimitMiddleware::class, CsrfMiddleware::class]
    );
    $router->register('/password/reset', 'Lukaisu\\Modules\\User\\Http\\UserController@resetPasswordForm', 'GET');
    $router->post(
        '/password/reset',
        'Lukaisu\\Modules\\User\\Http\\UserController@resetPassword',
        [AuthRateLimitMiddleware::class, CsrfMiddleware::class]
    );
    // Recovery-code reset (for accounts created without an email).
    $router->register(
        '/password/recover',
        'Lukaisu\\Modules\\User\\Http\\UserController@recoverWithCodeForm',
        'GET'
    );
    $router->post(
        '/password/recover',
        'Lukaisu\\Modules\\User\\Http\\UserController@recoverWithCode',
        [AuthRateLimitMiddleware::class, CsrfMiddleware::class]
    );

    // ==================== WORDPRESS INTEGRATION (PUBLIC) ====================

    // WordPress routes are public - they handle their own auth via WP tokens
    $router->register('/wordpress/start', 'Lukaisu\\Modules\\User\\Http\\WordPressController@start');
    $router->register('/wordpress/stop', 'Lukaisu\\Modules\\User\\Http\\WordPressController@stop');

    // ==================== GOOGLE OAUTH INTEGRATION (PUBLIC) ====================

    // Google OAuth routes are public - they handle their own auth via OAuth tokens.
    // The link-confirm POST writes to the session and changes account linkage,
    // so it must validate CSRF to block cross-site form submissions even though
    // it does not require AuthMiddleware (controller checks the pending-link session state).
    $router->register('/google/start', 'Lukaisu\\Modules\\User\\Http\\GoogleController@start');
    $router->register('/google/callback', 'Lukaisu\\Modules\\User\\Http\\GoogleController@callback');
    $router->register('/google/link-confirm', 'Lukaisu\\Modules\\User\\Http\\GoogleController@linkConfirm', 'GET');
    $router->post(
        '/google/link-confirm',
        'Lukaisu\\Modules\\User\\Http\\GoogleController@processLinkConfirm',
        [CsrfMiddleware::class]
    );

    // ==================== MICROSOFT OAUTH INTEGRATION (PUBLIC) ====================

    // Microsoft OAuth routes are public - they handle their own auth via OAuth tokens.
    // See the Google block above for why link-confirm POST needs CsrfMiddleware.
    $router->register('/microsoft/start', 'Lukaisu\\Modules\\User\\Http\\MicrosoftController@start');
    $router->register('/microsoft/callback', 'Lukaisu\\Modules\\User\\Http\\MicrosoftController@callback');
    $router->register('/microsoft/link-confirm', 'Lukaisu\\Modules\\User\\Http\\MicrosoftController@linkConfirm', 'GET');
    $router->post(
        '/microsoft/link-confirm',
        'Lukaisu\\Modules\\User\\Http\\MicrosoftController@processLinkConfirm',
        [CsrfMiddleware::class]
    );

    // ==================== API ROUTES ====================

    // Main API - use prefix to catch all sub-paths
    // Note: API handles its own authentication internally via ApiV1::validateAuth()
    // This allows /api/v1/auth/* endpoints to be public while protecting others.
    // CsrfMiddleware enforces token validation on POST/PUT/DELETE/PATCH for
    // cookie-authenticated callers; requests carrying a Bearer token are
    // exempted inside the middleware (the token itself defeats CSRF).
    // Support both /api/v1 (new) and /api.php/v1 (legacy) paths.
    // RateLimitMiddleware sits in front of CSRF so a flood of unsigned
    // requests gets dropped before we spend cycles hashing the token —
    // the middleware itself uses the per-endpoint window for /whisper
    // (5/15min) and the general default for everything else.
    $router->registerPrefixWithMiddleware(
        '/api/v1',
        'ApiController@v1',
        [RateLimitMiddleware::class, CsrfMiddleware::class]
    );
    $router->registerPrefixWithMiddleware(
        '/api.php/v1',
        'ApiController@v1',
        [RateLimitMiddleware::class, CsrfMiddleware::class]
    );

    // Translation APIs (PROTECTED) - used by authenticated users
    $router->registerWithMiddleware(
        '/api/translate',
        'Lukaisu\\Modules\\Dictionary\\Http\\TranslationController@translate',
        AUTH_MIDDLEWARE
    );
    $router->registerWithMiddleware(
        '/api/google',
        'Lukaisu\\Modules\\Dictionary\\Http\\TranslationController@google',
        AUTH_MIDDLEWARE
    );
    $router->registerWithMiddleware(
        '/api/glosbe',
        'Lukaisu\\Modules\\Dictionary\\Http\\TranslationController@glosbe',
        AUTH_MIDDLEWARE
    );

    // ==================== JOB-A CUT-OVER: PAGES -> BUNDLED CLIENT ====================
    // The reading/learning UI is no longer rendered by PHP views: these GET page
    // routes 302 to the equivalent bundle page under /app/ (BundleController
    // serves dist-app/, which talks to this server's /api/v1). Registered last so
    // they OVERRIDE the page-render handlers above for GET; the POST/JSON/DELETE
    // data routes on the same paths keep their controllers. Mirrors
    // src/frontend/app/router.ts bundledPageFor(). Removing a line here restores
    // the PHP page for that route (the views still exist until Job B/C land).
    $bundleRedirect = 'Lukaisu\\Shared\\Http\\BundleController@redirect';
    $router->get('/', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/index.php', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/connect', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/texts', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/text/{text:int}/read', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/text/read', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/texts/new', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/texts/{id:int}/edit', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/text/archived', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/text/archived/{id:int}/edit', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/text/check', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/text/{text:int}/print-plain', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/text/print-plain', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/words', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/words/edit', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/word/bulk-translate', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/word/upload', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/words/{id:int}/edit', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/languages', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/languages/new', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/languages/{id:int}/edit', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/languages/{id:int}/starter-vocab', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/tags', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/tags/text', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/feeds', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/feeds/manage', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/review', $bundleRedirect, AUTH_MIDDLEWARE);
    $router->get('/profile/preferences', $bundleRedirect, AUTH_MIDDLEWARE);

    // ==================== DEPRECATED ROUTES ====================
    // These legacy routes still work but emit Deprecation headers.
    // They will be removed in the next major version.

    $router->deprecate('/text/read', '/text/{text}/read');
    $router->deprecate('/text/display', '/text/{text}/display');
    $router->deprecate('/text/print-plain', '/text/{text}/print-plain');
    $router->deprecate('/text/edit', '/texts');
    $router->deprecate('/word/new', '/words/new');
    $router->deprecate('/word/show', '/word/{wid}');
    $router->deprecate('/vocabulary/term/status', '/vocabulary/term/{wid}/status');
    $router->deprecate('/feeds/edit', '/feeds/{id}/edit');
    $router->deprecate('/dictionaries', '/languages/{id}/dictionaries');
    $router->deprecate('/dictionaries/import', '/languages/{id}/dictionaries/import');
    $router->deprecatePrefix('/api.php/v1', '/api/v1');
}
