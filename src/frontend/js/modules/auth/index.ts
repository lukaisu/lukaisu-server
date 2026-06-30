/**
 * Auth Module - User authentication and registration.
 *
 * @license Unlicense <http://unlicense.org/>
 */

// Side-effect imports (pages)
import './pages/register_form';
import './pages/reset_password_form';
// The packaged-client connect/login flow is now the Svelte `ConnectPage` island
// (mounted by app/connect.ts on the bundle's index.html); the Alpine `clientAuth`
// renderer + its `client_auth.php` view were retired (the /connect route 302s to
// the bundle). register/reset stay Alpine until their own pages migrate.
