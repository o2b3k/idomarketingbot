[← Configuration](configuration.md) · [Back to README](../README.md)

# Security

Authentication and account security in `idomarketing` are provided by **Laravel Fortify**, a frontend-agnostic auth backend. This page describes how it's wired and the protections in place.

## Authentication Model

Fortify registers the auth routes and controllers — you will **not** find login/register/password routes in `routes/`. Instead, `app/Providers/FortifyServiceProvider.php` is the wiring hub. It:

- Maps each Fortify view to an Inertia page (`auth/login`, `auth/register`, `auth/forgot-password`, `auth/reset-password`, `auth/verify-email`, `auth/two-factor-challenge`, `auth/confirm-password`).
- Points user creation and password reset at custom actions in `app/Actions/Fortify/` (`CreateNewUser`, `ResetUserPassword`).
- Defines rate limiters for `login`, `two-factor`, and `passkeys`.

Enabled features (see [Configuration](configuration.md)):

| Feature | Description |
|---------|-------------|
| Registration | Self-service account creation |
| Password reset | Emailed reset links |
| Email verification | Verify address before accessing gated routes |
| Two-factor authentication | TOTP + recovery codes, with confirmation |
| Passkeys | WebAuthn passwordless sign-in |

## Two-Factor Authentication

Configured with `'confirm' => true` and `'confirmPassword' => true`, meaning:

- Enabling 2FA requires the user to **confirm** a TOTP code before it becomes active.
- Sensitive 2FA management requires a recent **password confirmation**.
- Recovery codes are provided for account recovery. Relevant UI: `manage-two-factor.tsx`, `two-factor-setup-modal.tsx`, `two-factor-recovery-codes.tsx`.

## Passkeys (WebAuthn)

Passwordless sign-in via passkeys (`@laravel/passkeys` / `laravel/pao`), with `'confirmPassword' => true` for management. The discovery endpoint is exposed at `.well-known/passkey-endpoints` (defined in `routes/settings.php`). Relevant UI: `manage-passkeys.tsx`, `passkey-register.tsx`, `passkey-verify.tsx`.

## Rate Limiting

Defined in `FortifyServiceProvider::configureRateLimiting()`:

| Limiter | Limit | Keyed by |
|---------|-------|----------|
| `login` | 5 / minute | lowercased username + IP |
| `two-factor` | 5 / minute | login session id |
| `passkeys` | 10 / minute | credential id (or session) + IP |

The password-change route (`routes/settings.php`) additionally uses `throttle:6,1`.

## Password Confirmation Gate

The account **security** settings page is protected by the `RequirePassword` middleware, so users must re-enter their password before viewing/altering sensitive settings. Password rules are centralized in `app/Concerns/PasswordValidationRules.php` and reused across Fortify actions and Form Requests.

## Secrets & Environment

- Never commit secrets. Keep credentials in `.env` (git-ignored); `.env.example` documents the keys only.
- Set `APP_DEBUG=false` in production to avoid leaking stack traces.
- MCP servers referenced in `.mcp.json` read `GITHUB_TOKEN` and `DATABASE_URL` from the environment — provide them only where needed.

## See Also

- [Configuration](configuration.md) — toggling auth features
- [Architecture](architecture.md) — how auth fits the overall structure
