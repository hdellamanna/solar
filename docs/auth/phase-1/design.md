# Auth Phase 1 — Email Verification Design Doc

**Scope:** PR1 of the FASE 4D auth split. Only email verification — no password reset, no 2FA.

## Goals

- New user registers → email is sent with verification link (60 min TTL) → user must click before logging in.
- Demo user (`demo@solar.app`) is auto-verified in the seeder so local dev keeps working.
- Middleware `EnsureEmailIsVerified` blocks all `auth` routes except `/email/verify`, `/logout`, `/profile/*`, `/email/verify/resend`.
- Rate-limit on resend: 1 per 30s, max 5 per hour.
- Resend mailer already wired (commit 599a6ce), RESEND_API_KEY in `.env` (not committed), `phpunit.xml` uses `array` mailer.

## Data model

### Migration: `2026_06_12_create_email_verification_tokens_table.php`

```php
Schema::create('email_verification_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('token_hash', 64);         // SHA-256 of raw token, never stored plain
    $table->timestamp('expires_at');
    $table->timestamp('consumed_at')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->string('user_agent', 255)->nullable();
    $table->timestamps();
    $table->index(['user_id', 'expires_at']);
});
```

### Model: `app/Models/EmailVerificationToken.php`

- `$table`, `$fillable = ['user_id', 'token_hash', 'expires_at', 'consumed_at', 'ip_address', 'user_agent']`
- `$casts = ['expires_at' => 'datetime', 'consumed_at' => 'datetime']`
- Relation: `user()` BelongsTo
- Method: `isValid(): bool` (not consumed, not expired)
- Static: `hashToken(string $raw): string` (sha256)
- Static: `generateForUser(User $user, ?Carbon $expiresAt = null): array{token, model}` (returns raw token + persisted model — raw token is the only way the user can use it)

### `User` model updates

Add relation: `emailVerificationTokens(): HasMany`. No other changes to `users` table.

## Service: `app/Services/Auth/EmailVerificationService.php`

```php
class EmailVerificationService
{
    public function sendVerificationEmail(User $user, ?string $ip = null, ?string $ua = null): void
    // Generates token, persists EmailVerificationToken, sends email via VerifyEmailMail.

    public function verify(string $rawToken): User
    // Hashes token, looks up active row, marks consumed, sets user.email_verified_at = now(), returns user.
    // Throws InvalidVerificationTokenException if not found / consumed / expired.

    public function canResend(User $user): bool
    // True if no token sent in last 30s, and fewer than 5 active tokens in last hour.
}
```

## Routes (`routes/web.php`)

All under `auth` middleware except verification flow itself:

```php
// Guest-or-auth routes (user can be logged-in-but-unverified)
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [VerifyEmailController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{token}', [VerifyEmailController::class, 'verify'])->name('verification.verify');
    Route::post('/email/verify/resend', [ResendVerificationController::class, 'store'])->name('verification.resend');
});

// Middleware applied to the existing auth routes
Route::middleware(['auth', 'verified'])->group(function () {
    // ... existing routes stay; just add 'verified' to the middleware chain
});
```

**Important:** the `email/verify` routes must be reachable by an authenticated-but-unverified user. So they go OUTSIDE the `verified` middleware but inside `auth`.

## Middleware: `app/Http/Middleware/EnsureEmailIsVerified.php`

- Alias: `verified` (Laravel convention)
- If `! $request->user()->hasVerifiedEmail()` → redirect to `route('verification.notice')` with flash `error`
- Bypass routes: `verification.verify`, `verification.notice`, `verification.resend`, `logout`
- Apply to ALL existing auth routes by adding `verified` to their middleware group

## Controllers

### `app/Http/Controllers/Auth/VerifyEmailController.php`

```php
class VerifyEmailController extends Controller
{
    public function notice(): Response|RedirectResponse
    // GET /email/verify — show VerifyEmailNotice.vue (already logged in but not verified)

    public function verify(Request $request, string $token): RedirectResponse
    // GET /email/verify/{token}
    // - If user already verified → redirect to dashboard with flash "Já verificado"
    // - Call EmailVerificationService::verify($token)
    // - On success: Auth::login($user) (in case they were not logged in), redirect to dashboard with flash success
    // - On exception: redirect to verification.notice with error flash
}
```

### `app/Http/Controllers/Auth/ResendVerificationController.php`

```php
class ResendVerificationController extends Controller
{
    public function __construct(private EmailVerificationService $service) {}

    public function store(ResendVerificationRequest $request): RedirectResponse
    // POST /email/verify/resend
    // - If already verified → redirect to dashboard
    // - If !$service->canResend($user) → back with error "Aguarde antes de reenviar"
    // - Else: send, back with success "Email reenviado"
}
```

### Update: `app/Http/Controllers/Auth/RegisterController.php`

```php
public function store(RegisterRequest $request): RedirectResponse
{
    $data = $request->validated();
    $user = User::create([...]);
    // DO NOT Auth::login here — they need to verify first.
    // Send verification email
    app(EmailVerificationService::class)->sendVerificationEmail($user, $request->ip(), $request->userAgent());
    // Log them in but middleware will block — they can only access /email/verify
    Auth::login($user);
    $request->session()->regenerate();
    return redirect()->route('verification.notice');
}
```

### Update: `app/Http/Controllers/Auth/LoginController.php`

After successful password check:

```php
if (! $user->hasVerifiedEmail()) {
    app(EmailVerificationService::class)->sendVerificationEmail($user, $request->ip(), $request->userAgent());
    Auth::login($user);
    return redirect()->route('verification.notice');
}
```

## Form Requests

- `ResendVerificationRequest.php` — authorize: `auth`, no rules (just `auth` check)

## Mail

### `app/Mail/VerifyEmailMail.php`

- Mailable, uses `Envelope` and `Content` patterns (Laravel 12+)
- Subject: "Confirme seu email — Solar Money"
- View: `resources/views/emails/verify-email.blade.php`
- Receives `user` and `verificationUrl` (signed URL with token, expires in 60 min)

### `resources/views/emails/verify-email.blade.php`

Plain HTML email (Blade + inline CSS — no Liquid Crystal, emails are restricted):

```html
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; margin: 0; padding: 32px; }
        .container { max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 40px; }
        .logo { text-align: center; font-size: 24px; font-weight: 800; background: linear-gradient(135deg, #FF8A3D 0%, #FFC93C 45%, #7c3aed 100%); -webkit-background-clip: text; color: transparent; }
        .headline { font-size: 22px; font-weight: 700; color: #0b0f1a; margin: 24px 0 8px; }
        .body { color: #475569; line-height: 1.6; margin-bottom: 24px; }
        .btn { display: inline-block; background: linear-gradient(180deg, #8b5cf6, #6d28d9); color: white; padding: 14px 28px; border-radius: 12px; text-decoration: none; font-weight: 600; box-shadow: 0 8px 24px -4px rgba(124, 58, 237, 0.4); }
        .muted { color: #94a3b8; font-size: 12px; margin-top: 24px; }
        .url { word-break: break-all; color: #94a3b8; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">Solar Money</div>
        <h1 class="headline">Confirme seu email</h1>
        <p class="body">Olá {{ $user->name }}, clique no botão abaixo para confirmar seu email e começar a usar o Solar Money. O link expira em 60 minutos.</p>
        <p style="text-align: center; margin: 32px 0;">
            <a href="{{ $verificationUrl }}" class="btn">Confirmar email</a>
        </p>
        <p class="muted">Ou cole este link no navegador:</p>
        <p class="url">{{ $verificationUrl }}</p>
        <p class="muted">Se você não criou essa conta, ignore este email.</p>
    </div>
</body>
</html>
```

## Vue views (frontend track)

### `resources/js/Pages/Auth/VerifyEmailNotice.vue`

Renders inside `GuestLayout` (no auth nav). Shows:
- Heading: "Confirme seu email"
- Subtitle: "Enviamos um link de verificação para `{{ user.email }}`. Clique para ativar sua conta."
- Big primary button: "Reenviar email" (POSTs to `verification.resend`)
- Helper text: "O link expira em 60 minutos."
- "Sair" link → logout
- Success/error flash from session

### `resources/js/Pages/Auth/VerifyEmailSuccess.vue`

NOT a separate route — the success state happens in Dashboard's flash banner. So we don't need a separate success page. The verify endpoint just redirects to dashboard with `flash.success = "Email confirmado! Bem-vindo."`.

### Update `resources/js/Pages/Auth/Register.vue`

After successful POST, the user is redirected to `/email/verify` automatically (server side). But we should also handle the case where the user submits and gets redirected — Inertia handles this. No UI changes needed in Register.vue itself.

### Update `resources/js/Pages/Auth/Login.vue`

- If login succeeds but email is unverified, the redirect to `/email/verify` happens server-side. The login page already has a generic "Processing" state. Add inline error display for `form.errors.email` containing "Verifique seu email".

### Update `resources/js/Layouts/AuthenticatedLayout.vue`

The user CAN reach the layout (because they're `auth` but maybe not `verified`). Add a dismissable amber banner at top: "Sua conta ainda não foi verificada. [Reenviar email]" — links to `verification.resend`. Banner hides when `user.email_verified_at` is set.

## Tests (tester track)

### `tests/Feature/Auth/EmailVerificationTest.php`

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

### `tests/Feature/Auth/AuthTest.php` updates

- Existing test `test_user_can_register_and_login` must be updated: the registration now redirects to `/email/verify` not `/dashboard`. Then the test should simulate clicking the verification link.

## Migration safety

- New table only (no changes to `users` table) — fully reversible
- No data loss
- Existing users (only `demo@solar.app` in dev) have `email_verified_at` already set by the seeder, so they continue to work

## File checklist

**Backend (track 1):**
- `database/migrations/2026_06_12_120000_create_email_verification_tokens_table.php`
- `app/Models/EmailVerificationToken.php`
- `app/Models/User.php` (add relation)
- `app/Services/Auth/EmailVerificationService.php`
- `app/Http/Middleware/EnsureEmailIsVerified.php`
- `app/Http/Controllers/Auth/VerifyEmailController.php`
- `app/Http/Controllers/Auth/ResendVerificationController.php`
- `app/Http/Requests/Auth/ResendVerificationRequest.php`
- `app/Http/Controllers/Auth/RegisterController.php` (update)
- `app/Http/Controllers/Auth/LoginController.php` (update)
- `app/Mail/VerifyEmailMail.php`
- `routes/web.php` (add routes + verified middleware)
- `bootstrap/app.php` (register `verified` alias)
- `app/Providers/AppServiceProvider.php` or `bootstrap/app.php` for route alias

**Frontend (track 2):**
- `resources/js/Pages/Auth/VerifyEmailNotice.vue`
- `resources/js/Pages/Auth/Login.vue` (update error display)
- `resources/js/Layouts/AuthenticatedLayout.vue` (add banner)
- `resources/views/emails/verify-email.blade.php`

**Tests (track 3):**
- `tests/Feature/Auth/EmailVerificationTest.php`
- `tests/Feature/AuthTest.php` (update existing)

## Constraints / gotchas

- **Worktree per track** (lesson from FASE 5): each parallel track must use its own git worktree, not `/tmp/solar` directly, to avoid collisions.
- **Order of routes matters**: declare the verification routes BEFORE the `verified` middleware group, otherwise the middleware blocks them.
- **Token storage**: only hash (sha256) stored; raw token is the secret. The verify URL is `/email/verify/{rawToken}`. Never log raw tokens.
- **Email subject encoding**: ASCII only, no accents in subject line. Body is fine.
- **Resend rate limit**: 30s throttle + 5/hour cap. Use cache with TTL key.
- **Demo user**: seeder already sets `email_verified_at = now()` — verify that tests confirm this.
- **No Liquid Crystal in emails**: emails render in Gmail/Outlook/etc., no `backdrop-filter` support. Plain HTML + inline CSS only.

## After PR1 ships

- Merge to main
- Trigger PR2 (Password Reset) which reuses `VerifyEmailMail` patterns and the same token infrastructure (extend `email_verification_tokens` table with `purpose` column? Or new `password_reset_tokens` table — let PR2 decide, but recommend reusing the same table with `purpose` enum to keep it simple)
