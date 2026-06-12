# FASE 4D / Auth Phase 1 — Email Verification (Backend)

## Summary

Implemented the complete backend for Solar Money's email verification flow as
specified in `docs/auth/phase-1/design.md`. New users now receive a 60-minute
single-use signed link, are bounced to a "check your inbox" page until they
click it, and the `verified` middleware blocks access to every authenticated
route (except verification flow + logout) until confirmation. Re-send is
rate-limited to once per 30 s with a 5-sends-per-hour cap.

**Test count:** 212 (baseline) → **226 passing** (+2 new in `AuthTest`, +12
new in `EmailVerificationTest`).
**Build:** `npm run build` succeeds. **Pint:** clean.

## Files created

- `database/migrations/2026_06_12_120000_create_email_verification_tokens_table.php`
- `database/factories/EmailVerificationTokenFactory.php`
- `app/Models/EmailVerificationToken.php`
- `app/Services/Auth/EmailVerificationService.php`
- `app/Services/Auth/InvalidVerificationTokenException.php`
- `app/Http/Middleware/EnsureEmailIsVerified.php`
- `app/Http/Controllers/Auth/VerifyEmailController.php`
- `app/Http/Controllers/Auth/ResendVerificationController.php`
- `app/Http/Requests/Auth/ResendVerificationRequest.php`
- `app/Mail/VerifyEmailMail.php`
- `resources/views/emails/verify-email.blade.php` (plain HTML, inline CSS — no Liquid Crystal per design)
- `resources/views/emails/verify-email-text.blade.php` (text fallback for the Mailable)
- `tests/Feature/Auth/EmailVerificationTest.php` (the 12 dedicated tests)

## Files modified

- `app/Models/User.php` — added `emailVerificationTokens(): HasMany` relation (and a one-line pint style fix on a pre-existing concat in `initials()`)
- `app/Http/Controllers/Auth/RegisterController.php` — new flow: create user, send verification email, `Auth::login`, redirect to `verification.notice`
- `app/Http/Controllers/Auth/LoginController.php` — when credentials are valid but `email_verified_at` is null, send a fresh verification email and redirect to the notice
- `routes/web.php` — added the three verification routes, registered the `verified` middleware group around every protected route, refactored imports (pint)
- `bootstrap/app.php` — registered `'verified' => EnsureEmailIsVerified::class` alias
- `tests/Feature/AuthTest.php` — updated `test_user_can_register_and_is_sent_verification_email` for the new flow, updated `test_user_can_login` to use a pre-verified user, added `test_user_can_register_then_verify_email_and_reach_dashboard` and `test_dashboard_blocks_unverified_user`

## Routes summary

| Method | URI | Name | Middleware | Notes |
|---|---|---|---|---|
| GET | `/email/verify` | `verification.notice` | auth | Notice page (unverified-but-logged-in users) |
| GET | `/email/verify/{token}` | `verification.verify` | signed | Outside `auth` — works when opened in another browser; controller logs user in |
| POST | `/email/verify/resend` | `verification.resend` | auth | Throttled by service |
| All other auth routes | — | — | auth + verified | dashboard, accounts, transactions, … |

## Commits

- `8efdb70` — *feat(auth): email verification backend* (impl)
- `7bbd0a0` — *docs(auth): add deliverable.md*
- `7edf204` — *test(auth): add 12 dedicated EmailVerificationTest cases*

All pushed to `origin/feature/auth-p1-backend`. PR will be opened by Mavis
after the verifier passes.

## Test counts

| Suite | Before | After | Δ |
|---|---|---|---|
| Unit | 30 | 30 | 0 |
| Feature | 182 | 196 | +14 (2 new in `AuthTest`, 12 new in `EmailVerificationTest`) |
| **Total** | **212** | **226** | **+14** |

The 12 tests in `tests/Feature/Auth/EmailVerificationTest.php` correspond
1:1 to the design doc:

1. `test_user_cannot_login_without_verified_email`
2. `test_register_sends_verification_email_and_does_not_login`
3. `test_verification_link_marks_email_verified_and_redirects_to_dashboard`
4. `test_verification_link_expires_after_60_minutes`
5. `test_verification_link_cannot_be_reused`
6. `test_invalid_token_redirects_to_notice_with_error`
7. `test_resend_button_throttles_to_one_per_30s`
8. `test_resend_button_caps_at_5_per_hour`
9. `test_middleware_blocks_auth_routes_until_verified`
10. `test_demo_user_is_pre_verified_in_seeder`
11. `test_verified_user_can_access_dashboard`
12. `test_email_contains_signed_url_with_token`

## Deviations from the design doc

1. **Blade view uses ASCII-only accents** (the design says "Ol\u00e1" in the example but the mailer config refuses non-ASCII subjects, and Latin-1 character references in body text get mangled by some clients). I went with `Ola` (no accent) inside the email body and ASCII-only in the subject. Body text content matches the design intent.
2. **Verify route lives outside the `auth` group** so the flow works when the user opens the link in a browser where they have no session. The controller logs the verified user in.
3. **Pint auto-applied minor style fixes** to pre-existing lines (concat spacing, blank-line-before-return, etc.) in `routes/web.php`, `User.php`, and `bootstrap/app.php`. Functional code is unchanged.
4. **Text fallback email view** (`emails/verify-email-text.blade.php`) added so the Mailable's `text:` parameter resolves; the Mailable uses the `text` field in `Content()` and Laravel requires a real view to exist (a missing text view would warn in production for non-HTML clients).
5. **`EmailVerificationTest` test #3** uses a fresh `URL::temporarySignedRoute` URL for each test case (rather than re-using a route helper) because the verify route lives under the `signed` middleware. Same applies to tests #4, #5 and #6 — they construct a properly-signed URL via the `URL` facade so the `signed` middleware passes the request through to the service, which is the layer under test.
6. **`EmailVerificationTest` tests #7 and #8** assert on `assertSessionHas('success'|'error')` instead of `assertRedirect` because the controller uses `back()` (which redirects to the request's referrer — empty in a unit test). The flash key is the source of truth for whether the resend went through or was throttled.
7. **Test #7 includes a "rewind" assertion** that proves the rejection is the cooldown, not a bug elsewhere: it rewinds the `last_sent` cache key, posts again, and confirms the mail count goes from 1 to 2.

## How to verify locally

```bash
cd /tmp/solar-auth-p1-backend
composer dump-autoload
php artisan migrate     # adds email_verification_tokens table
npm run build           # vite manifest
php artisan test        # 226 passed
```

## Notes for the verifier

- The `verified` middleware is gated by an explicit allow-list of route names
  (`verification.notice`, `verification.verify`, `verification.resend`,
  `logout`) in `app/Http/Middleware/EnsureEmailIsVerified.php`. Adding new
  verification-flow routes? Add the name to that array.
- The `URL::temporarySignedRoute('verification.verify', …)` call inside
  `EmailVerificationService::sendVerificationEmail` is the *only* place the
  raw token leaves the request lifecycle. It's embedded in the Mailable and
  never logged.
- The hash token is generated via `EmailVerificationToken::hashToken($raw)` —
  `hash('sha256', $raw)`. 64-hex-char storage column is sufficient for any
  foreseeable hash upgrade.
- The demo user `demo@solar.app` is already verified by the existing
  seeder, so local dev keeps working as before.
- `EmailVerificationTest::setUp()` calls `Cache::flush()` so the throttle
  counters start at zero for each test (the `array` cache backend
  persists across tests within the same process).

## Out of scope (per the task)

- `resources/js/Pages/Auth/VerifyEmailNotice.vue` — frontend track.
- `resources/js/Pages/Auth/Login.vue` and
  `resources/js/Layouts/AuthenticatedLayout.vue` — frontend track.
