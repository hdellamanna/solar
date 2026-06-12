# Auth Phase 2 — Password Reset (Forgot Password) Design Doc

**Scope:** PR2 of the FASE 4D auth split. Forgot-password flow with the same
60-min signed-link pattern used by email verification. **No 2FA yet** — that's
PR3.

## Goals

- User clicks "Esqueci minha senha" on the login page → enters email → gets a
  reset link via Resend (60-min TTL) → clicks the link → sets a new password →
  redirected to login with success flash.
- Existing verified user can always reset (the email is the proof of identity).
- Unverified users are silently allowed to reset too (since they need a password
  to log in and verify — and the reset email serves as a soft re-verify path).
  This is intentional: do not block on `email_verified_at`.
- Old active reset tokens for the same user are invalidated when a new one
  is issued.
- Rate limit: 1 per 30s, max 5 per hour, same shape as the resend verification
  throttle.

## Reuse strategy

The existing `email_verification_tokens` table is extended with a `purpose`
column rather than creating a new `password_reset_tokens` table. Reasons:

1. The shape is identical: 60-min single-use SHA-256-hashed bearer token.
2. Lookup-by-hash logic is identical.
3. Cleanup of expired rows is a single query.
4. Avoids table sprawl in a year from now.

The model class stays `EmailVerificationToken` (no rename) — the `purpose`
column is the discriminator. We can rename in a later PR if a `OneTimeToken`
generic name becomes clearer. For now, the table name and class name are
historical artifacts.

## Data model

### Migration: `2026_06_12_150000_add_purpose_to_email_verification_tokens.php`

```php
Schema::table('email_verification_tokens', function (Blueprint $table) {
    $table->string('purpose', 32)->default('email_verification')->after('user_id');
    $table->index(['user_id', 'purpose', 'expires_at'], 'evt_user_purpose_expires_idx');
    // Drop the older 2-col index — the new 3-col one covers it
    $table->dropIndex(['user_id', 'expires_at']);
});

DB::statement("ALTER TABLE email_verification_tokens ADD CONSTRAINT evt_purpose_chk CHECK (purpose IN ('email_verification', 'password_reset'))");
```

### Model update: `app/Models/EmailVerificationToken.php`

```php
public const PURPOSE_EMAIL_VERIFICATION = 'email_verification';
public const PURPOSE_PASSWORD_RESET = 'password_reset';

protected $fillable = [..., 'purpose'];  // add

public function scopeForPurpose(Builder $q, string $purpose): Builder
{
    return $q->where('purpose', $purpose);
}
```

`generateForUser()` now takes a `purpose` argument (default
`PURPOSE_EMAIL_VERIFICATION` to preserve existing callers).

## Service: `app/Services/Auth/PasswordResetService.php`

New service. The existing `EmailVerificationService` is **not** extended — it
keeps its single responsibility (verify emails) so it's easier to reason
about. Cross-cutting concerns (hashing, throttling) are duplicated as private
helpers to keep each service self-contained.

```php
class PasswordResetService
{
    public const TTL_MINUTES = 60;

    public function requestReset(string $email, ?string $ip = null, ?string $ua = null): bool
    // Always returns true (no user-enumeration oracle). If the email matches a
    // real user, generate a token and send the email. Otherwise silently
    // return true so the response time + UX is identical.

    public function resetPassword(string $rawToken, string $newPassword): User
    // Throws InvalidResetTokenException on bad/expired/used token. Otherwise
    // marks token consumed, sets $user->password = Hash::make($newPassword),
    // invalidates all remember-me sessions (rotation), and returns the user.

    public function canRequestReset(string $email): bool
    // Returns true if no token sent in last 30s, and fewer than 5 active
    // tokens in last hour, for this email.
}
```

The throttle uses the same `Cache::tags(['password-reset'])` + `Cache::add()`
pattern as `EmailVerificationService::canResend`.

## Routes (`routes/web.php`)

All under the `guest` middleware group (anyone — even unauthenticated — can
hit these):

```php
Route::middleware('guest')->group(function () {
    // ... existing login + register ...

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->name('password.update');
});
```

The `/reset-password/{token}` route is intentionally outside the `auth`
middleware — the user is presumed logged out when they click the link (it's
their first time back).

## Controllers

### `app/Http/Controllers/Auth/PasswordResetLinkController.php`

```php
public function create(): Response
// GET /forgot-password — show the email-entry form (Forgotten password.vue)

public function store(PasswordResetLinkRequest $request): RedirectResponse
// POST /forgot-password
// - Validate email exists (don't tell the user)
// - Call PasswordResetService::requestReset
// - Always redirect back with success flash "Se o email existir, enviaremos um link"
//   (security: no user enumeration)
```

### `app/Http/Controllers/Auth/NewPasswordController.php`

```php
public function create(Request $request, string $token): Response
// GET /reset-password/{token}
// - If token is invalid/expired/used → redirect to forgot-password with
//   error flash
// - Else show the form (NewPassword.vue) with the token as a hidden input

public function store(NewPasswordRequest $request): RedirectResponse
// POST /reset-password
// - Call PasswordResetService::resetPassword($token, $newPassword)
// - Log the user in automatically (so they don't have to retype email
//   + new password on the next screen)
// - Redirect to dashboard with success flash "Senha redefinida."
```

## Form Requests

- `app/Http/Requests/Auth/PasswordResetLinkRequest.php` — only `email` (required, email, max 160)
- `app/Http/Requests/Auth/NewPasswordRequest.php` — `token` (required, string), `password` (required, confirmed, Password::min(8))

## Mail

### `app/Mail/PasswordResetMail.php`

Mailable with Envelope (subject `Redefina sua senha · Solar Money`) and
Content (view `resources/views/emails/password-reset.blade.php`).

Receives `user` and `resetUrl` (signed URL with token, expires 60 min).

### `resources/views/emails/password-reset.blade.php`

Visual twin of `verify-email.blade.php` for brand consistency:

```html
<!DOCTYPE html>
<html>
<head>
    <style>
        /* Same inline CSS as verify-email.blade.php */
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">Solar Money</div>
        <h1 class="headline">Redefina sua senha</h1>
        <p class="body">Olá {{ $user->name }}, clique no botão abaixo para redefinir sua senha. O link expira em 60 minutos.</p>
        <p style="text-align: center; margin: 32px 0;">
            <a href="{{ $resetUrl }}" class="btn">Redefinir senha</a>
        </p>
        <p class="muted">Ou cole este link no navegador:</p>
        <p class="url">{{ $resetUrl }}</p>
        <p class="muted">Se você não solicitou isso, ignore este email — sua senha não será alterada.</p>
    </div>
</body>
</html>
```

## Vue views (frontend track)

### `resources/js/Pages/Auth/ForgottenPassword.vue`

Renders inside `GuestLayout` (no auth nav). Shows:
- Heading: "Esqueci minha senha"
- Subtitle: "Informe seu email e enviaremos um link para redefinir."
- Input: email (uses `.input-glass`)
- Primary button: "Enviar link de redefinição" (calls `PasswordResetLinkRequest`)
- Success flash: "Se o email existir, enviaremos um link em alguns minutos."
- Back link to login

### `resources/js/Pages/Auth/NewPassword.vue`

Renders inside `GuestLayout`. Shows:
- Heading: "Criar nova senha"
- Hidden input: `token` (from URL)
- Inputs: new password + confirm (use `.input-glass`)
- Submit button: "Redefinir senha" (calls `NewPasswordRequest`)
- Error states: token invalid/expired → show "Link inválido ou expirado" + button to request a new one

### Update `resources/js/Pages/Auth/Login.vue`

Add "Esqueci" link next to the password label that points to
`route('password.request')`. Already exists per PR1 (the placeholder href
becomes real).

## Tests (tester track)

### `tests/Feature/Auth/PasswordResetTest.php`

1. `test_user_can_request_password_reset_link` — POST forgot-password with valid email → 302 back → success flash, email queued/sent, token row created with `purpose='password_reset'`
2. `test_password_reset_request_does_not_reveal_user_existence` — POST with unknown email → 302 back with SAME flash (no error), NO email sent, NO token row
3. `test_password_reset_request_throttles_to_one_per_30s` — second POST within 30s → success but no new token, no email
4. `test_password_reset_request_caps_at_5_per_hour` — 6th request in 1h → success but no new token
5. `test_password_reset_link_marks_token_consumed_on_use` — POST reset with valid token → token row has `consumed_at`, password changed
6. `test_password_reset_link_expires_after_60_minutes` — `travel(61 minutes)` then POST → 422 with error
7. `test_password_reset_link_cannot_be_reused` — POST twice with same token → second fails
8. `test_password_reset_invalidates_other_active_tokens` — request 2 resets, use 1st → 2nd is invalid
9. `test_password_reset_does_not_block_unverified_users` — unverified user can still reset (intentional)
10. `test_password_reset_loglocks_user_in_after_success` — assertAuthenticatedAs after successful reset
11. `test_password_reset_email_contains_signed_url` — Mail::fake, assert email has $resetUrl with signature
12. `test_old_email_verification_tokens_still_work` — regression: existing flow not broken by table change
13. `test_invalid_token_redirects_to_forgot_password_with_error` — GET /reset-password/bad-token → redirect to forgot-password

### Update `tests/Feature/AuthTest.php`

- Existing `test_user_can_login` should keep passing (no changes to login).
- The test for "remember me" might need to be updated if we invalidate remember-me on reset — keep it as-is for now and let the test verifier flag if needed.

## Migration safety

- `ALTER TABLE` adding a column with default value — safe, no data loss.
- CHECK constraint on `purpose` — both old and new rows get `email_verification` by default. Old verification tokens continue to work.
- Index changes are reversible.
- Existing users (only `demo@solar.app` in dev) are unaffected.

## File checklist

**Backend (track 1):**
- `database/migrations/2026_06_12_150000_add_purpose_to_email_verification_tokens.php`
- `app/Models/EmailVerificationToken.php` (add purpose column + constants + scope)
- `app/Services/Auth/PasswordResetService.php` (+ InvalidResetTokenException)
- `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- `app/Http/Controllers/Auth/NewPasswordController.php`
- `app/Http/Requests/Auth/PasswordResetLinkRequest.php`
- `app/Http/Requests/Auth/NewPasswordRequest.php`
- `app/Mail/PasswordResetMail.php`
- `routes/web.php` (add 4 routes inside the existing `guest` group)

**Frontend (track 2):**
- `resources/js/Pages/Auth/ForgottenPassword.vue`
- `resources/js/Pages/Auth/NewPassword.vue`
- `resources/js/Pages/Auth/Login.vue` (replace placeholder "Esqueci" href with route)
- `resources/views/emails/password-reset.blade.php` (+ text fallback)

**Tests (track 3):**
- `tests/Feature/Auth/PasswordResetTest.php`

## Constraints / gotchas (carry-over from PR1)

- **Worktree per track** (lesson from FASE 5): each parallel track uses its
  own git worktree, not `/tmp/solar` directly, to avoid collisions.
- **Email subject encoding**: ASCII only, no accents in subject line. Body is fine.
- **Raw token never stored** (only SHA-256 hash). The reset URL is
  `/reset-password/{rawToken}`. Never log raw tokens.
- **No Liquid Crystal in emails**: plain HTML + inline CSS only.
- **Inertia 3.x `router` import crashes the page** (PR1 lesson). Use
  `useForm` for any form submission, never `router.post()`.
- **Do not change the existing `email_verification_tokens` table semantics**:
  the new `purpose` column is the discriminator. Old rows with no purpose
  default to `email_verification`.
- **Security: no user enumeration**. POST /forgot-password always returns
  the same flash message regardless of whether the email exists.

## After PR2 ships

- Merge to main
- Tag v0.8.0
- Trigger PR3 (2FA + Trusted Devices) — reuses the same `EmailVerificationToken`
  table with a new `purpose = 'two_factor'`. The `password_reset_tokens`
  discussion is closed: we never created a separate table.
