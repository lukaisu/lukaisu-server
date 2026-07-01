/**
 * Auth Module - User authentication and registration.
 *
 * @license Unlicense <http://unlicense.org/>
 */

// Side-effect imports (pages)
//
// Every auth screen is now a token-API Svelte island mounted by its own
// app/*.ts entry: login (LoginPage), register (RegisterPage), the password flows
// (Forgot/Reset/RecoverPasswordPage), and the packaged-client connect flow
// (ConnectPage). The Alpine `register_form` / `reset_password_form` / `client_auth`
// renderers were all retired, so this barrel has no side effects left.
export {};
