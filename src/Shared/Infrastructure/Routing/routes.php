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
 * The server is headless (Option A of docs-src/server/frontend-relocation.md):
 * it exposes `/api/v1` and nothing else renders a reading/learning UI. The
 * bundled client is built in the sibling `lukaisu` repo (Phase M moved the
 * frontend there) and ships in the Lukaisu mobile app, talking to a server's
 * `/api/v1` remotely with a bearer token — this server never served it
 * directly (R6f). The two remaining pieces of server-rendered HTML are
 * inherently server-side: the OAuth account-link-confirm forms
 * (Google/Microsoft) below.
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
    // ==================== TEXT ROUTES (PROTECTED) ====================
    // The reading/learning UI (reader, texts list, edit forms, print, parse
    // preview, archived list) has no server route: it is served exclusively by
    // a connected client (mobile app or any /api/v1 consumer) talking to the
    // JSON API. Only the mutating, non-API routes below remain server-side.

    // Delete text (RESTful route): DELETE /texts/123
    $router->delete('/texts/{id:int}', 'Lukaisu\\Modules\\Text\\Http\\TextController@delete', AUTH_MIDDLEWARE);

    // Archive text (RESTful route): POST /texts/123/archive
    $router->post('/texts/{id:int}/archive', 'Lukaisu\\Modules\\Text\\Http\\TextController@archive', AUTH_MIDDLEWARE);

    // Unarchive text (RESTful route): POST /texts/123/unarchive
    $router->post(
        '/texts/{id:int}/unarchive',
        'Lukaisu\\Modules\\Text\\Http\\TextController@unarchive',
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
    // TermStatusController, TermApiController, TermImportController. Term
    // create/edit/list forms have no server route (client-side, via /api/v1).

    // Delete word (RESTful route): DELETE /words/123
    $router->delete(
        '/words/{id:int}',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermEditController@deleteWord',
        AUTH_MIDDLEWARE
    );

    // Inline edit (TermEditController)
    $router->registerWithMiddleware(
        '/word/inline-edit',
        'Lukaisu\\Modules\\Vocabulary\\Http\\TermEditController@inlineEdit',
        AUTH_MIDDLEWARE
    );

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

    // ==================== LANGUAGE ROUTES (PROTECTED) ====================
    // Language create/edit/delete/refresh and the language list have no server
    // route: they are served client-side through /api/v1/languages
    // (LanguageApiHandler).

    // Starter vocabulary (shown after language creation) is client-side too
    // (/api/v1/languages/{id}/starter-vocab/{config,import,enrich}), except this
    // one server-side skip redirect (a Location bounce to /texts/new).
    $router->get(
        '/languages/{id:int}/starter-vocab/skip',
        'Lukaisu\\Modules\\Vocabulary\\Http\\StarterVocabController@skip',
        AUTH_MIDDLEWARE
    );

    // ==================== TAG ROUTES (PROTECTED) ====================
    // Tag management (list/create/rename/delete) has no server route: it is
    // served client-side through /api/v1/tags/{term,text} (TagApiHandler).

    // ==================== FEED ROUTES (PROTECTED) ====================
    // The feed list and new/edit forms have no server route: they are served
    // client-side through /api/v1/feeds* (FeedApiHandler).

    // Delete feed (RESTful route): DELETE /feeds/123
    $router->delete('/feeds/{id:int}', 'Lukaisu\\Modules\\Feed\\Http\\FeedController@deleteFeed', AUTH_MIDDLEWARE);

    // ==================== BOOK ROUTES (PROTECTED) ====================
    // Book management (list/detail) has no server route: it is served
    // client-side through /api/v1/books (BookApiHandler).

    // ==================== LOCAL DICTIONARY ROUTES (PROTECTED) ====================
    // The local-dictionaries list has no server route: it is served client-side
    // through the local-dictionaries REST API. The multipart file import stays
    // as POST /api/v1/local-dictionaries/import (DictionaryApiHandler ->
    // DictionaryController@processImport).

    // ==================== ADMIN ROUTES (ADMIN ONLY) ====================
    // The server admin browser UI was dropped under the headless cut (Option A):
    // dashboard, backup/restore, the DB wizard, install-demo, server-data, user
    // management, and settings are managed via /api/v1 / CLI / the future Python
    // edge, not server-rendered or client-served pages. AdminController +
    // UserManagementController + their views are gone. AdminApiHandler + the
    // admin/user-management use cases are kept for that API/CLI path.

    // Statistics (User module) - legacy /admin/statistics redirects to /profile/statistics
    $router->registerWithMiddleware(
        '/admin/statistics',
        'Lukaisu\\Modules\\User\\Http\\StatisticsController@redirectFromAdmin',
        AUTH_MIDDLEWARE
    );

    // ==================== USER PROFILE (AUTH REQUIRED) ====================
    // The profile page (name / email / password) was dropped under the headless
    // cut (Option A): profile is managed via /api/v1 / CLI. Preferences and
    // statistics have no server route either — served client-side through
    // /api/v1/settings and /api/v1/activity/* (intensity, frequency, streak,
    // calendar).

    // ==================== AUTHENTICATION ROUTES (PUBLIC) ====================
    // All auth routes use UserController from the User module. There is no
    // server-rendered or server-served login/register/password UI: a connected
    // client authenticates entirely through the token API
    // (POST /api/v1/auth/{login,register,password/forgot,password/reset,
    // password/recover}), independent of the cookie-session routes below.

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
    // These cookie-session POST handlers are independent of the token-API
    // password routes a connected client actually uses (see above).
    $router->post(
        '/password/forgot',
        'Lukaisu\\Modules\\User\\Http\\UserController@forgotPassword',
        [AuthRateLimitMiddleware::class, CsrfMiddleware::class]
    );
    $router->post(
        '/password/reset',
        'Lukaisu\\Modules\\User\\Http\\UserController@resetPassword',
        [AuthRateLimitMiddleware::class, CsrfMiddleware::class]
    );
    // Recovery-code reset (for accounts created without an email).
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
    // The link-confirm GET/POST is the last server-rendered HTML in the app:
    // shown mid-login when the Google email already has an account, it renders a
    // password form (google_link_confirm.php) to link the two. The POST writes
    // to the session and changes account linkage, so it must validate CSRF to
    // block cross-site form submissions even though it does not require
    // AuthMiddleware (controller checks the pending-link session state).
    $router->register('/google/start', 'Lukaisu\\Modules\\User\\Http\\GoogleController@start');
    $router->register('/google/callback', 'Lukaisu\\Modules\\User\\Http\\GoogleController@callback');
    $router->register('/google/link-confirm', 'Lukaisu\\Modules\\User\\Http\\GoogleController@linkConfirm', 'GET');
    $router->post(
        '/google/link-confirm',
        'Lukaisu\\Modules\\User\\Http\\GoogleController@processLinkConfirm',
        [CsrfMiddleware::class]
    );

    // ==================== MICROSOFT OAUTH INTEGRATION (PUBLIC) ====================

    // Microsoft OAuth routes are public - they handle their own auth via OAuth
    // tokens. The link-confirm GET/POST is the other server-rendered page
    // (microsoft_link_confirm.php); see the Google block above for why the POST
    // needs CsrfMiddleware.
    $router->register('/microsoft/start', 'Lukaisu\\Modules\\User\\Http\\MicrosoftController@start');
    $router->register('/microsoft/callback', 'Lukaisu\\Modules\\User\\Http\\MicrosoftController@callback');
    $router->register(
        '/microsoft/link-confirm',
        'Lukaisu\\Modules\\User\\Http\\MicrosoftController@linkConfirm',
        'GET'
    );
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

    // The server-rendered translate popups (/api/translate, /api/google,
    // /api/glosbe -> TranslationController) were dropped under the headless cut:
    // the client does dictionary lookup + translation on-device (glosbe /
    // LibreTranslate directly + /api/v1/local-dictionaries/lookup).

    // ==================== DEPRECATED ROUTES ====================
    // These legacy routes still work but emit Deprecation headers.
    // They will be removed in the next major version.

    $router->deprecate('/vocabulary/term/status', '/vocabulary/term/{wid}/status');
    $router->deprecatePrefix('/api.php/v1', '/api/v1');
}
