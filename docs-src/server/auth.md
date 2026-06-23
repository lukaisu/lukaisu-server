# Auth — Design Note

> **Status: DESIGN ONLY.** Auth is **not** in the F-Droid milestone and should
> not be built into the edge service yet. Local-first single-device use needs
> **no auth at all**. This note records the plan so it's ready when sync lands.

## When auth is (and isn't) needed

| Scenario | Auth? |
|---|---|
| First-time user, no server, reading offline (the milestone) | **None.** |
| User points the app at a personal edge service for NLP/TTS/content | **None.** The edge is stateless, stores no user data, and exposes nothing private. CORS + the SSRF guard are the only gating. |
| User opts into **multi-device sync** | **Required.** The server now stores multiple users' synced data and must scope every read/write to the authenticated user. |

So auth is strictly a **dependency of sync** ([sync-contract.md](./sync-contract.md)),
not of the NLP/outbound edge. Don't add it until sync exists.

## Reference model (already implemented in the legacy PHP)

The PHP `UserApiHandler` (`src/Modules/User/Http/UserApiHandler.php`) and the
`users` table already define a complete model. **Reuse the concepts; reimplement
minimally in Python — do not port the PHP wholesale.** The relevant pieces:

- **Bearer tokens.** `users.UsApiToken` (+ `UsApiTokenExpires`), sent as
  `Authorization: Bearer <token>`, validated per request. This is the right
  primitive for a mobile client.
- **Register / login.** `POST /auth/register`, `POST /auth/login` →
  issue a token. Password hashing via `UsPasswordHash`.
- **Token refresh / logout.** `POST /auth/refresh`, `POST /auth/logout`.
- **Recovery code.** `UsRecoveryCodeHash` — an account-recovery secret shown
  once at registration. Important because a self-hoster has no "forgot password"
  email by default.
- **ALTCHA proof-of-work** (`GET /auth/altcha-challenge`) gates registration
  against bots without a third-party CAPTCHA — keep it for any public instance.
- **OAuth columns** (`UsGoogleId`, `UsMicrosoftId`, `UsWordPressId`) exist but
  are **out of scope** for the minimal reimplementation.

## Minimal Python plan (when sync arrives)

A new `/auth` router on the edge service, backed by a small users store (the
first persistent state the server owns — until now the edge is stateless):

```
POST /auth/register   { username, password, [altcha] } -> { token, recovery_code }
POST /auth/login      { username, password }            -> { token, expires }
POST /auth/refresh    (Bearer)                           -> { token, expires }
POST /auth/logout     (Bearer)                           -> { ok }
GET  /auth/me         (Bearer)                           -> { user }
```

Principles:

- **Single-user stays auth-free.** If the instance is configured single-user
  (the common self-host case), the auth gate is a no-op and `/sync` operates on
  the lone user — mirroring the legacy `MULTI_USER_ENABLED` behavior.
- **Tokens are opaque + expiring**, stored hashed server-side. The client keeps
  the token in secure device storage and attaches it to `/sync` calls only.
- **Scope everything by `user_id`.** Auth's only job here is to resolve a token
  to a `user_id` that the sync layer uses to filter every row. ULIDs are not an
  authorization boundary; the `user_id` check is (see sync-contract.md).
- **Don't gate the NLP/outbound edge behind auth.** Those endpoints carry no
  user data; requiring login there would break the "server is optional" promise
  for users who just want better CJK parsing or content discovery.

## Out of scope

- OAuth / social login.
- Email-based password reset (recovery code covers self-host).
- Per-endpoint roles beyond user/admin.

These can come later; none are needed for first-cut sync.
