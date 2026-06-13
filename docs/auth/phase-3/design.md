# Auth Phase 3 — 2FA + Trusted Devices (TOTP) Design Doc

**Scope:** PR3 (and final) of the FASE 4D auth split. Time-based one-time
passwords (TOTP, RFC 6238) with optional trusted-device cookies. Hybrid
smart enforcement: always on for new devices, optional toggle for known
devices. **No WebAuthn / passkeys yet** — that's a future phase.

## Goals

- A user can enroll 2FA in Settings: an authenticator app (Google
  Authenticator, 1Password, Authy, etc.) scans a QR code, the user
  confirms with a 6-digit code, and the service stores the encrypted
  TOTP secret + a set of 10 single-use recovery codes.
- After login, if the user has 2FA enabled AND the device is not
  trusted, the user is redirected to a `verify-2fa` challenge page
  before reaching the dashboard.
- The challenge accepts a 6-digit TOTP code OR one of the 10 recovery
  codes. Either path marks the device as trusted for 90 days (rotating
  httpOnly cookie) and proceeds to the dashboard.
- The user can opt OUT of the trusted-device cookie by unchecking a
  "Trust this device" box on the challenge page; in that case the
  cookie is NOT set and the user is challenged every login.
- The user can revoke all trusted devices from Settings → Security,
  which clears the cookie and the trusted-device table.
- The 2FA flow is **destructive on disable**: turning 2FA off wipes
  the encrypted TOTP secret and all recovery codes. The user must
  confirm by re-entering their password.

## Reuse strategy

- The `email_verification_tokens` table already supports a
  `purpose` column. We add `purpose = 'two_factor'` to the
  CHECK constraint and use it for the 2FA **enrollment** challenge
  and the **disable** confirmation (one-time 60-min link sent by
  email to confirm the action). This keeps the security pattern
  consistent across all three flows (verify / reset / 2fa-enroll).
- The TOTP **login** challenge does NOT use the email-token table —
  it uses session state only, since the user is mid-login.
- A new `user_two_factor` table holds the encrypted TOTP secret,
  enabled-at timestamp, and last-used counter. A new
  `user_recovery_codes` table holds the hashed recovery codes
  (SHA-256, one row per code so we can mark them consumed
  individually). A new `trusted_devices` table holds the
  device cookies (selector + validator hash, last_seen_at,
  friendly name, ip).
- The `EnsureTwoFactorVerified` middleware sits between
  `auth` and `verified` in the route group stack. A user
  who is `auth` but not `two_factor_verified` is bounced
  to the challenge page (route `two-factor.challenge`),
  except for the explicit allowlist (`two-factor.*`,
  `logout`).

## Data model

### Migration: `2026_06_12_180000_add_two_factor_to_users_and_devices.php`

Three operations, in this order:

1. Extend the `purpose` CHECK constraint on
   `email_verification_tokens` to include `'two_factor_enroll'` and
   `'two_factor_disable'`. (See "Reuse strategy" above — both are
   one-time email-confirmed actions, not the live login challenge.)
2. Create `user_two_factor`:
   ```php
   Schema::create('user_two_factor', function (Blueprint $table) {
       $table->id();
       $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
       $table->text('secret_encrypted'); // app-key-encrypted TOTP secret
       $table->unsignedInteger('last_counter')->default(0);
       $table->timestamp('enabled_at');
       $table->timestamp('confirmed_at')->nullable();
       $table->timestamps();
   });
   ```
3. Create `user_recovery_codes`:
   ```php
   Schema::create('user_recovery_codes', function (Blueprint $table) {
       $table->id();
       $table->foreignId('user_id')->constrained()->cascadeOnDelete();
       $table->string('code_hash', 64); // SHA-256 hex
       $table->timestamp('consumed_at')->nullable();
       $table->timestamps();
       $table->index(['user_id', 'consumed_at']);
   });
   ```
4. Create `trusted_devices`:
   ```php
   Schema::create('trusted_devices', function (Blueprint $table) {
       $table->id();
       $table->foreignId('user_id')->constrained()->cascadeOnDelete();
       $table->string('selector', 32)->unique(); // random, used to look up
       $table->string('validator_hash', 64);     // SHA-256(validator)
       $table->string('friendly_name')->nullable();
       $table->string('ip', 45)->nullable();
       $table->string('user_agent', 500)->nullable();
       $table->timestamp('last_seen_at');
       $table->timestamp('expires_at');
       $table->timestamps();
       $table->index(['user_id', 'expires_at']);
   });
   ```

### Model updates

- `App\Models\User`: add `twoFactor()`, `recoveryCodes()`,
  `trustedDevices()` relations. Add `hasTwoFactorEnabled()`,
  `isTwoFactorVerified()` (session-based, set by the
  challenge controller), and `markTwoFactorVerified()`.
- `App\Models\UserTwoFactor`: thin Eloquent wrapper, exposes
  `encryptedSecret` / `secret` (decrypted via
  `Crypt::decryptString`).
- `App\Models\RecoveryCode`: `isConsumed()`, `markConsumed()`.
- `App\Models\TrustedDevice`: `isExpired()`, `touchSeen()`,
  `verify($validator): bool`, `matches($request)`.

## Services

### `App\Services\Auth\TwoFactorService`

- `generateSecret(): string` — returns a 32-char base32 TOTP secret
  (uses `pragmarx/google2fa-laravel` or `OTPHP\TOTP` — see "Library
  choice" below). Reuse via the static helper `Str::random(32)`
  uppercased, base32-encoded via the chosen library.
- `currentOtp(string $encryptedSecret): string` — wraps the library's
  `currentOTP()` for the live challenge.
- `verifyCode(string $encryptedSecret, string $code, int &$newCounter): bool`
  — calls the library's `verifyKey($secret, $code)` with a
  ±1 window (current ± previous 30s step), and bumps `last_counter`
  to prevent replay of the same TOTP step.
- `provisioningUri(string $encryptedSecret, string $email): string`
  — returns the `otpauth://totp/Solar%20Money:email?secret=...&issuer=Solar%20Money`
  URL for the QR code.
- `encryptSecret(string $plain): string` / `decryptSecret(string $enc): string`
  — wraps `Crypt::encryptString` / `decryptString` so the
  `APP_KEY` rotation story stays in one place.

### `App\Services\Auth\TrustedDeviceService`

- `issue(User $user, Request $request, ?string $friendlyName = null): void`
  — generates a random 32-byte selector + 64-byte validator,
  inserts a `trusted_devices` row, and sets an httpOnly cookie
  named `solar_trusted` (value = `selector:validator`) with a
  90-day expiry. The cookie is `Secure`, `HttpOnly`, `SameSite=Lax`.
- `revokeAll(User $user): int` — deletes all `trusted_devices`
  rows for the user and clears the cookie. Returns the count.
- `revokeOne(int $id, User $user): bool` — deletes one row.
- `verify(Request $request, User $user): bool` — looks up the row
  by selector, compares the SHA-256 of the validator, checks
  expiry, and (if matched) calls `touchSeen()` to update
  `last_seen_at`. Returns true on success.
- `cleanup(): int` — `DELETE` where `expires_at < now()`. Scheduled
  via Laravel's scheduler to run daily; not invoked inline.

### `App\Services\Auth\TwoFactorEnrollmentService`

Wraps the **email-confirmed** half of 2FA (enable / disable
confirmations). The TOTP enrollment and disable are both gated by
a 60-min one-time email link, same pattern as the verify and reset
flows.

- `beginEnable(User $user): string` — mints a `purpose = 'two_factor_enroll'`
  token row, emails the user a link to `two-factor.enable.confirm/{token}`,
  returns the raw token (used to build the URL — same SHA-256 hash
  pattern as the other two flows).
- `confirmEnable(string $rawToken, string $totpCode): User` — verifies
  the token, verifies the TOTP code against the freshly-generated
  secret, persists the encrypted secret + 10 recovery codes, marks
  the token consumed, returns the user.
- `beginDisable(User $user): void` — mints a `purpose = 'two_factor_disable'`
  token, emails the user a link to `two-factor.disable.confirm/{token}`.
- `confirmDisable(string $rawToken, string $password): User` —
  verifies the token, re-checks the password (defense in depth),
  wipes the encrypted secret + all recovery codes + all trusted
  devices, marks the token consumed, returns the user.

## Middleware

### `App\Http\Middleware\EnsureTwoFactorVerified`

Sits between `auth` and `verified`. Reads the user, checks
`hasTwoFactorEnabled()`. If 2FA is not enabled, the middleware
is a no-op. If 2FA is enabled, the middleware checks
`isTwoFactorVerified()` (session flag set by the challenge
controller). If the session flag is false, it checks the
`trusted_devices` cookie via `TrustedDeviceService::verify()`
and, if matched, sets the session flag and continues. Otherwise
it redirects to `two-factor.challenge` with a flash message.

The middleware exempts routes whose name starts with `two-factor.`
(challenge / enable / disable) and `logout`.

## Routes (`routes/web.php`)

```php
// Inside the auth group (requires login, redirects to /login if not)
Route::middleware(['auth', 'verified', 'two_factor'])->group(function () {
    // ... existing app routes ...
});

// 2FA challenge: requires auth + verified, NOT two_factor_verified.
// Lives outside the `two_factor` middleware so the user can reach it.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/two-factor/challenge', [TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [TwoFactorChallengeController::class, 'store'])
        ->name('two-factor.verify');

    // Email-confirmed enable / disable confirmations
    Route::get('/two-factor/enable/confirm/{token}', [TwoFactorEnableController::class, 'confirmEnable'])
        ->name('two-factor.enable.confirm');
    Route::get('/two-factor/disable/confirm/{token}', [TwoFactorDisableController::class, 'confirmDisable'])
        ->name('two-factor.disable.confirm');
});

// Settings routes for 2FA — inside auth + verified + two_factor
Route::middleware(['auth', 'verified', 'two_factor'])->group(function () {
    Route::post('/settings/security/two-factor/enable', [TwoFactorEnableController::class, 'beginEnable'])
        ->name('two-factor.enable.begin');
    Route::post('/settings/security/two-factor/disable', [TwoFactorDisableController::class, 'beginDisable'])
        ->name('two-factor.disable.begin');

    Route::delete('/settings/security/trusted-devices/{id}', [TrustedDeviceController::class, 'destroy'])
        ->name('trusted-devices.destroy');
    Route::delete('/settings/security/trusted-devices', [TrustedDeviceController::class, 'destroyAll'])
        ->name('trusted-devices.destroy-all');
});
```

The two GET routes (`enable.confirm`, `disable.confirm`) are
deliberately outside the `auth` middleware so the user can click
the link in their email on a different device without being
bounced to `/login`. They do their own session-less
"token-present" check, then redirect to the right place (back
to `/login` with success flash, or to the challenge page).

## Controllers

### `TwoFactorChallengeController`
- `create()`: show the 6-digit code input form, plus a recovery
  code input form, plus a "Trust this device" checkbox.
- `store(VerifyTwoFactorRequest $request)`:
  1. Look up the user from `Auth::user()`.
  2. Try TOTP first via `TwoFactorService::verifyCode()`.
  3. If TOTP fails, try recovery code (SHA-256 match against an
     unconsumed row, then `markConsumed()`).
  4. If neither works, flash error, back to the challenge.
  5. If either works, set the session flag, optionally issue a
     trusted-device cookie (if checkbox checked), redirect to
     `dashboard` with success flash "Verificação concluída."

### `TwoFactorEnableController`
- `beginEnable(Request $request)`: calls
  `TwoFactorEnrollmentService::beginEnable()`. Returns the
  generated secret + provisioning URI as JSON to the Vue
  page (so the QR can be rendered client-side from the URI).
  Empties the response on the next call after confirmation.
- `confirmEnable(string $token)`: GET-only. Resolves the token,
  generates a fresh TOTP secret, returns a Vue page with
  `{secret, qrUri, token}` props. The Vue page prompts for a
  6-digit confirmation code and POSTs back to a
  `confirmEnableStore` action (see "Vue flow" below).

Wait — the design above has `confirmEnable` as a GET and the
final confirmation as a POST. Let me clarify: the GET is a
**token-validated page render** (no auth needed, the token IS
the proof). The POST is the actual confirmation (auth not
required either — the user mid-enrollment is on a fresh browser
or a different device; the token is the credential).

So:

- `GET /two-factor/enable/confirm/{token}` — page render
  (auth not required).
- `POST /two-factor/enable/confirm` — final confirmation
  (token + 6-digit code, no auth).

Same shape for disable.

### `TwoFactorDisableController`
- `beginDisable(Request $request)`: requires the user's password
  (`password` field in the form) as defense in depth, then calls
  `TwoFactorEnrollmentService::beginDisable()`. Emails the link.
- `confirmDisable(string $token)`: GET-only, page render. Vue
  page asks the user to re-confirm by typing their password.
- `confirmDisableStore(ConfirmDisableRequest $request)`: POST,
  takes `{token, password}`, calls
  `confirmDisable($token, $password)`, logs the user out (so
  they re-auth from scratch without 2FA), redirects to `/login`
  with flash "2FA desativado. Faça login novamente."

### `TrustedDeviceController`
- `destroy(Request $request, int $id)`: revoke one.
- `destroyAll(Request $request)`: revoke all + clear cookie.

## Frontend (Phase 3 Vue)

### `resources/js/Pages/Settings/Security.vue`
A new "Security" section under Settings. Shows:
- Current 2FA status (enabled / disabled)
- If enabled: when enrolled, last verification time
- A list of trusted devices (name, IP, last seen, expires) with
  per-row "Revoke" + a "Revoke all" button
- Buttons to "Enable 2FA" or "Disable 2FA" depending on state

### `resources/js/Pages/Auth/TwoFactorChallenge.vue`
- "Insira o código de 6 dígitos do seu app autenticador"
- TOTP code input (6 digits, autocomplete=one-time-code)
- Recovery code input (8 chars, optional secondary path)
- "Confiar neste dispositivo por 90 dias" checkbox
- Submit button "Verificar"

### Enable 2FA flow (modal / multi-step)
1. User clicks "Ativar 2FA" in Settings.
2. Vue shows: "Vamos enviar um link de confirmação pro seu
   email." Submit button → POST `/two-factor/enable/begin`.
3. User opens email, clicks link → lands on
   `/two-factor/enable/confirm/{token}` (Vue page).
4. Page shows: QR code (rendered from `provisioningUri`),
   manual secret (click-to-copy), 6-digit confirmation input.
5. User types code, POSTs `{token, code}` to
   `/two-factor/enable/confirm`.
6. On success: show 10 recovery codes (one-time display,
   click-to-copy each, "I've saved them" acknowledgment
   checkbox required), then "Concluir" → redirect to
   Security settings.

### Disable 2FA flow
1. User clicks "Desativar 2FA" in Settings, types password
   in a confirm dialog.
2. POST `/two-factor/disable/begin` with `{password}`. Email
   sent.
3. User clicks link → `/two-factor/disable/confirm/{token}`.
4. Vue asks for password again (defense in depth) → POST
   `{token, password}` to `/two-factor/disable/confirm`.
5. 2FA + recovery codes + trusted devices wiped, user logged
   out, redirect to `/login`.

## Tests (tester track)

### `tests/Feature/Auth/TwoFactorEnrollmentTest.php`
1. `test_user_can_begin_2fa_enrollment` — POST enable-begin →
   token row created with `purpose='two_factor_enroll'`, email
   sent.
2. `test_enable_enrollment_requires_authenticated_user` —
   anonymous POST → 401/redirect.
3. `test_enable_enrollment_email_contains_signed_url` —
   Mail::fake, assert URL has `?expires=` and `&signature=`.
4. `test_user_can_confirm_2fa_enrollment_with_valid_totp` —
   GET confirm page renders, POST with correct TOTP code →
   `user_two_factor` row created, 10 `user_recovery_codes`
   rows created, token consumed.
5. `test_enable_confirmation_rejects_invalid_totp_code` —
   wrong code → 422, no `user_two_factor` row.
6. `test_enable_confirmation_rejects_consumed_token` — second
   POST with same token → 422.
7. `test_enable_confirmation_rejects_expired_token` —
   `travel(61 minutes)` → 422.
8. `test_enable_clears_2fa_on_disable` — full enable → disable
   flow → no `user_two_factor` row, no recovery codes.

### `tests/Feature/Auth/TwoFactorChallengeTest.php`
9. `test_user_with_2fa_enabled_is_redirected_to_challenge_after_login` —
   enable 2FA, log in → 302 to `two-factor.challenge`, dashboard
   not accessible.
10. `test_user_can_complete_challenge_with_valid_totp` — POST
    challenge with correct TOTP → 302 to dashboard, session flag
    set, `email_verified_at` unchanged.
11. `test_user_can_complete_challenge_with_recovery_code` —
    POST with one of the 10 recovery codes → 302 to dashboard,
    the row's `consumed_at` is set.
12. `test_challenge_rejects_invalid_totp_and_recovery_code` —
    POST with wrong TOTP AND wrong recovery code → 422 with
    error flash, dashboard still locked.
13. `test_trusted_device_cookie_bypasses_challenge_on_next_login` —
    enable + log in + complete challenge with "Trust" checked
    → log out + log in again → 200 dashboard (no challenge
    redirect).
14. `test_trusted_device_cookie_can_be_revoked` — enable + log
    in + challenge + revoke-all in settings → log out + log in
    again → 302 to challenge.
15. `test_2fa_disabled_user_skips_challenge` — log in without
    2FA → 200 dashboard, no challenge redirect.

### `tests/Feature/Auth/TwoFactorDisableTest.php`
16. `test_user_can_begin_2fa_disable` — POST disable-begin with
    correct password → 302, token row created with
    `purpose='two_factor_disable'`, email sent.
17. `test_disable_begin_rejects_wrong_password` — wrong password
    → 422.
18. `test_user_can_confirm_2fa_disable` — full flow → user
    logged out, `user_two_factor` row deleted, recovery codes
    deleted, trusted devices deleted, redirect to `/login`.

### `tests/Feature/Auth/TwoFactorTokenPurposeTest.php`
19. `test_purpose_column_accepts_two_factor_enroll` — direct
    DB insert with `purpose='two_factor_enroll'` works.
20. `test_purpose_column_accepts_two_factor_disable` — same.
21. `test_purpose_column_rejects_unknown_value` — direct insert
    with `purpose='bogus'` → CHECK constraint violation.
22. `test_two_factor_tokens_do_not_leak_into_password_reset_query` —
    when a user requests a password reset, only
    `purpose='password_reset'` rows are returned (not the
    `two_factor_*` ones).

Total: **22 dedicated tests** in 4 test files.

## Library choice

Two viable TOTP libraries for PHP:

1. **pragmarx/google2fa** + **pragmarx/google2fa-laravel**: Battle-tested,
   well-known, supports both PHP 8 and Laravel 13. Mature QR code
   generation via `BaconQrCode`. The default choice.
2. **spomky-labs/otphp**: Modern, smaller, more focused (just TOTP/HOTP,
   no Laravel coupling). The dependency we'd use if we ever want to
   support WebAuthn / passkeys / push — but we don't need that yet.

**Decision: pragmarx/google2fa-laravel.** The team has used it
before, it ships with the QR code generation we need for the
enable flow, and it works with PHP 8.4 + Laravel 13. If we ever
add WebAuthn, we'll evaluate `spomky-labs/otphp` then.

## File checklist

**Backend (track 1):**
- `database/migrations/2026_06_12_180000_add_two_factor_to_users_and_devices.php`
- `app/Models/UserTwoFactor.php`, `RecoveryCode.php`, `TrustedDevice.php`
- `app/Models/User.php` (add relations + helpers)
- `app/Services/Auth/TwoFactorService.php`
- `app/Services/Auth/TrustedDeviceService.php`
- `app/Services/Auth/TwoFactorEnrollmentService.php`
- `app/Http/Middleware/EnsureTwoFactorVerified.php`
- `app/Http/Controllers/Auth/TwoFactorChallengeController.php`
- `app/Http/Controllers/Auth/TwoFactorEnableController.php`
- `app/Http/Controllers/Auth/TwoFactorDisableController.php`
- `app/Http/Controllers/Auth/TrustedDeviceController.php`
- `app/Http/Requests/Auth/VerifyTwoFactorRequest.php`
- `app/Http/Requests/Auth/ConfirmTwoFactorEnableRequest.php`
- `app/Http/Requests/Auth/BeginTwoFactorDisableRequest.php`
- `app/Http/Requests/Auth/ConfirmTwoFactorDisableRequest.php`
- `app/Mail/TwoFactorEnableMail.php`
- `app/Mail/TwoFactorDisableMail.php`
- `resources/views/emails/two-factor-enable.blade.php`
- `resources/views/emails/two-factor-disable.blade.php`
- `routes/web.php` (add the 2FA route group inside the existing auth
  group + the 2 routes outside for the email-confirmed links)
- `bootstrap/app.php` (register `two_factor` middleware alias)
- `composer.json` (add `pragmarx/google2fa-laravel`)

**Frontend (track 2):**
- `resources/js/Pages/Settings/Security.vue`
- `resources/js/Pages/Auth/TwoFactorChallenge.vue`
- `resources/js/Pages/Auth/TwoFactorEnableConfirm.vue`
- `resources/js/Pages/Auth/TwoFactorDisableConfirm.vue`
- `resources/js/Layouts/AuthenticatedLayout.vue` (add nav link to
  Settings → Security)
- `resources/js/Pages/Settings/Index.vue` (add Security section, if
  it doesn't already exist; create the file if needed)
- `resources/js/Pages/Auth/Login.vue` — the "redirigir a
  /two-factor/challenge" flow is automatic via the middleware
  (no UI change needed)

**Tests (track 3):**
- `tests/Feature/Auth/TwoFactorEnrollmentTest.php` (8 cases)
- `tests/Feature/Auth/TwoFactorChallengeTest.php` (7 cases)
- `tests/Feature/Auth/TwoFactorDisableTest.php` (3 cases)
- `tests/Feature/Auth/TwoFactorTokenPurposeTest.php` (4 cases)

## Constraints / gotchas (carry-over from PR1 + PR2)

- **Worktree per track** (lesson from FASE 5 + PR1 + PR2).
- **Vendor symlink workaround** (lesson from PR2): in each worktree,
  `rm -rf vendor && cp -R /tmp/solar/vendor vendor` and set
  `APP_BASE_PATH=$(pwd)` in `.env`. The prompt must spell this out.
- **Email subject encoding**: ASCII only, no accents in subject line.
- **No Liquid Crystal in emails**: plain HTML + inline CSS only.
- **Inertia 3.x `router` import crashes the page** (PR1 lesson):
  use `useForm` for all form submissions.
- **Reuse the single token table** for `two_factor_enroll` and
  `two_factor_disable`. Extend the `purpose` CHECK constraint.
  PR1's `EmailVerificationService` and PR2's
  `PasswordResetService` already use the `scopeForPurpose` and
  `hashToken` / `generateForUser` helpers — PR3's services MUST
  reuse them, NOT create their own hash/lookup logic.
- **SHA-256 hash before storing** for the email-token table.
- **Defense in depth on disable**: password re-prompt + email
  link confirmation.
- **TOTP secret must be `Crypt::encryptString`-ed at rest** (uses
  the app key). Recovery codes are SHA-256 hashed, same as
  email-token rows.
- **Replay protection on TOTP**: the `last_counter` field
  prevents the same 30s step from being accepted twice.
- **Cookie attributes**: `solar_trusted` is httpOnly, secure, sameSite=Lax,
  90 days.
- **Do NOT install `spomky-labs/otphp` or any WebAuthn library** —
  the scope is TOTP only. PR3 is the last in FASE 4D.

## Composer dependency to add

```json
"require": {
    "pragmarx/google2fa-laravel": "^2.0"
}
```

`composer require pragmarx/google2fa-laravel` should resolve cleanly
on PHP 8.4 + Laravel 13 (the maintainer tracks Laravel releases
within a few weeks of GA).

## After PR3 ships

- FASE 4D is complete (verify, reset, 2FA). The auth surface
  is now competitive with what Linear, GitHub, or Notion offer
  out of the box.
- Move to FASE 5 (Investments, Debts, AI categorize, PWA) and
  FASE 6 (multi-currency, couples, OFX/CSV, OCR). These are
  the next milestones on the roadmap.
