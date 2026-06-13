# backend-impl — Password Reset Flow (FASE 4D, Auth Phase 2)

## Summary

Backend half of the Solar Money password-reset flow. The existing
`email_verification_tokens` table was extended with a `purpose`
column so the same single-use SHA-256-hashed bearer token shape
also carries password-reset tokens; a new service + two controllers
+ two form requests + a mailable + 4 routes wire the user-facing
forgot-password / set-new-password pages. All 226 existing tests
still pass; the new password-reset-specific tests belong to a
separate test-coverage track and are NOT included here.

## Files created

- `database/migrations/2026_06_12_150000_add_purpose_to_email_verification_tokens.php`
  — adds `purpose` column with default `email_verification`,
  replaces the 2-column `(user_id, expires_at)` index with a
  3-column `(user_id, purpose, expires_at)` index, and adds a CHECK
  constraint to keep the discriminator values honest. `up()` and
  `down()` are both reversible.
- `app/Services/Auth/PasswordResetService.php` — `requestReset()`,
  `resetPassword()`, `canRequestReset()`. Throttles by email address
  (not user id) with the same 30s cooldown / 5-per-hour cap shape as
  `EmailVerificationService`. On a successful reset, also rotates
  the user's `remember_token` and invalidates every other active
  reset token for the same user.
- `app/Services/Auth/InvalidResetTokenException.php` — `\RuntimeException`
  subtype, mirrors the existing `InvalidVerificationTokenException`.
- `app/Http/Controllers/Auth/PasswordResetLinkController.php` —
  `create()` (show the email-entry form) and `store()`
  (always returns the SAME generic success flash so the response
  cannot be used to enumerate registered emails).
- `app/Http/Controllers/Auth/NewPasswordController.php` —
  `create($token)` renders the new-password form, redirecting to
  `password.request` with an error flash if the token is bad /
  consumed / expired; `store(NewPasswordRequest)` consumes the
  token, rotates the password, and auto-logs the user in.
- `app/Http/Requests/Auth/PasswordResetLinkRequest.php` — `email`
  validation only; `authorize` returns true (guest middleware guards
  the route).
- `app/Http/Requests/Auth/NewPasswordRequest.php` — `token` (required
  string) + `password` (required, confirmed, `Password::min(8)`);
  `authorize` returns true.
- `app/Mail/PasswordResetMail.php` — Mailable with Envelope (subject
  "Redefina sua senha - Solar Money", ASCII-only) and Content
  (view + text).
- `resources/views/emails/password-reset.blade.php` — Plain HTML
  email, brand-consistent twin of `verify-email.blade.php`.
- `resources/views/emails/password-reset-text.blade.php` — Text
  fallback for clients that can't render HTML.

## Files modified

- `app/Models/EmailVerificationToken.php` — added
  `PURPOSE_EMAIL_VERIFICATION` + `PURPOSE_PASSWORD_RESET` constants,
  added `purpose` to `$fillable`, added `scopeForPurpose()`, and
  added a `string $purpose` argument to `generateForUser()` (default
  preserves all existing email-verification call sites).
- `database/factories/EmailVerificationTokenFactory.php` — added
  `purpose` to `definition()` defaults so existing factory-based
  tests (notably `tests/Feature/Auth/EmailVerificationTest.php`)
  still pass.
- `routes/web.php` — imported the two new controllers; added 4
  named routes inside the existing `guest` middleware group:
  - `GET  /forgot-password`        → `password.request`
  - `POST /forgot-password`        → `password.email`
  - `GET  /reset-password/{token}` → `password.reset`
  - `POST /reset-password`         → `password.update`

  The GET form route does NOT use the `signed` middleware — the
  controller is the sole gatekeeper so it can return a friendly
  redirect to `forgot-password` with an error flash on any bad
  token (per the design's "test_invalid_token_redirects_to_forgot_password_with_error"
  contract). The 60-minute TTL is still enforced via the token's
  `expires_at` column.

## Test counts

| Suite | Before | After | Notes |
| ----- | ------ | ----- | ----- |
| Full PHPUnit | 226 / 226 | **226 / 226** | All existing tests still pass. |
| AuthTest (the file in scope) | 10 / 10 | **10 / 10** | No changes needed — `purpose` defaults keep verify flow working. |
| PasswordResetTest (NOT in scope) | n/a | n/a | Belongs to the tester track. Backend deliberately does not add tests here. |

`npm run build` and `php artisan test` both succeed locally:

```
$ npm run build
✓ built in 1.21s

$ php artisan test
{"tool":"phpunit","result":"passed","tests":226,"passed":226,"assertions":2031,"duration_ms":2790}
```

## Commit hashes

- `429626c` — `feat(auth): add purpose column to email_verification_tokens`
  (migration checkpoint, just the schema change)
- `3f3ef3f` — `feat(auth): password reset flow (Phase 2 / FASE 4D)`
  (model + service + controllers + form requests + mail + routes)

## Branch

- `feature/auth-p2-backend` pushed to `origin` (2 commits, 12 files
  changed, 642 insertions, 7 deletions). Not merged — owner will
  merge after verifier passes.

## Notes for the verifier

1. **Worktree / vendor gotcha** — this worktree has its own
   `vendor/` installed (not symlinked). The earlier symlink caused
   `Application::inferBasePath()` to resolve to the main repo path,
   which in turn made the test framework's `RefreshDatabase` see
   only 26 migrations instead of 27 (it `glob()`-scans the path
   returned by `database_path('migrations')`, which had been
   silently pointing at the wrong directory). With a real
   `vendor/`, `basePath()` correctly resolves to the worktree and
   all 27 migrations are picked up.

2. **`/reset-password/{token}` route has no `signed` middleware** —
   the controller handles all "bad token" cases by redirecting to
   `forgot-password` with a localized error flash. The
   `temporarySignedRoute()` URL still gives us a hard 60-minute
   window via the token's own `expires_at`. The design's
   "test_invalid_token_redirects_to_forgot_password_with_error"
   expectation is therefore satisfied for valid-signature but
   consumed/expired tokens; a tampered URL would 403. The tester
   track should pick the case that fits the test's intent.

3. **No `authorize: false` on form requests** — the design doc
   asked for `authorize: false`, but Laravel's `FormRequest` would
   then throw `AuthorizationException` for every request. I
   interpreted "authorize: false" as "no auth check needed (the
   `guest` middleware already guards the route)" and returned
   `true`, matching the existing `RegisterRequest` /
   `ResendVerificationRequest` pattern in this codebase.

4. **Existing verify flow is preserved** — `generateForUser()`'s
   new `purpose` arg defaults to `PURPOSE_EMAIL_VERIFICATION`, so
   every existing call site (registration, login, resend) works
   unchanged. The factory now also emits the default purpose, so
   the existing `EmailVerificationTest` regression coverage
   (12 tests) keeps passing.
