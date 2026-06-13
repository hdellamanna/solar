# FASE Polish — observability, resilience, refactor, UX (v0.10.0)

## Goals

Three orthogonal improvements shipped together as v0.10.0:

1. **Observability** — structured JSON logging with request-id, Sentry-ready
   error tracking, `/up` health endpoint that checks DB + queue + mail.
2. **Resilience** — real rate limiting on every auth route (forgot-password,
   verify, 2FA challenge, 2FA recovery code), with 429 responses and
   `Retry-After` headers. CSRF explicit on API routes.
3. **Refactor** — extract the shared token lifecycle
   (`EmailVerificationService`, `PasswordResetService`, `TwoFactorEnrollmentService`)
   into a single `BearerTokenService` base + thin per-purpose adapters. README
   updated to reflect v0.9.0 reality.
4. **UX** — Apple-style polish on the 2FA Settings + Challenge pages (mesh
   canvas, explicit success toasts, count-down confirmation on disable).

## Reuse strategy

- Laravel 13 ships `RateLimiter` facade with per-key backoff. Use it
  directly — no new dep.
- Laravel 13 ships `RateLimiter::for(...)` registration in
  `App\Providers\RouteServiceProvider` (or in the new `App\Providers\RateLimitServiceProvider`
  we'll create in `bootstrap/app.php`).
- The token service base is purely internal — no public API change.
  Each existing service (`EmailVerificationService`,
  `PasswordResetService`, `TwoFactorEnrollmentService`) becomes a thin
  wrapper that picks its `purpose` + `meta` + handler closure.
- Mesh canvas is already global in `app.blade.php` — just remove the
  override on the 2FA layouts that hides it (none should hide it; the
  previous V2 looked "white" because the `.glass` opacity was too high).

## Data model

No new tables. Two new env-only configs:

```dotenv
# Logging
LOG_CHANNEL_STACK=json               # was: single
LOG_REQUEST_HEADER=X-Request-Id     # auto-generated if missing

# Rate limits (per-IP unless noted)
RATE_LIMIT_VERIFY_PER_MIN=10         # POST /email/verify/resend
RATE_LIMIT_FORGOT_PER_MIN=5          # POST /forgot-password
RATE_LIMIT_RESET_PER_MIN=5           # POST /reset-password
RATE_LIMIT_2FA_CHALLENGE_PER_MIN=10  # POST /two-factor/challenge
RATE_LIMIT_2FA_RECOVERY_PER_MIN=3    # POST /two-factor/challenge (recovery)
RATE_LIMIT_LOGIN_PER_MIN=10          # POST /login (already in Laravel default)
```

## Service refactor

### `App\Services\Auth\BearerTokenService` (new)

Single owner of the token lifecycle. Methods:

- `mint(User $user, string $purpose, ?Closure $metaCallback = null): array`
  Returns `['raw' => string, 'row' => EmailVerificationToken]`. Persists
  the row with `purpose`, SHA-256 hash of the raw token, 60-min
  expiry, and the result of `$metaCallback($row, $raw)` stored in
  the existing `meta` JSON column.
- `consume(string $rawToken, string $purpose, Closure $handler): mixed`
  Throws `InvalidTokenException` on bad/expired/used. On success:
  marks the token consumed, calls `$handler($row, $user)` with the
  resolved user. The closure pattern lets each purpose do its
  post-consume work (e.g. enable 2FA persists the secret;
  reset password hashes a new password; verify sets
  `email_verified_at`).
- `canResend(string $email, string $purpose): bool` — 1-per-30s +
  max-5-per-hour throttle (cache key prefix `bearer-token:throttle:{purpose}:{email-hash}`).
- `resendThrottleKey(string $email, string $purpose): string` — used
  by the throttle.

### Thin adapters

```php
class EmailVerificationService
{
    public function __construct(private BearerTokenService $tokens) {}
    public function sendVerification(User $user): string { /* mints via $tokens */ }
    public function verify(string $rawToken): bool { /* consumes via $tokens */ }
}

class PasswordResetService
{
    public function __construct(private BearerTokenService $tokens) {}
    public function requestReset(string $email): bool { /* mints via $tokens */ }
    public function resetPassword(string $raw, string $new): User { /* consumes + hashes */ }
}

class TwoFactorEnrollmentService
{
    public function __construct(private BearerTokenService $tokens) {}
    public function beginEnable(User $user): void { /* mints via $tokens */ }
    public function beginDisable(User $user): void { /* mints via $tokens */ }
    // confirm methods stay — they own the action-after-consume logic
}
```

The public surface of each adapter is unchanged, so controllers
keep working untouched. The tests for each flow should still pass
without modification (they exercise the public surface).

## Rate limiting

In `bootstrap/app.php`, register 6 named limiters in
`->withMiddleware`:

```php
$middleware->throttleApi(); // baseline 60/min
$middleware->throttle('verify', 'verify');
$middleware->throttle('forgot', 'forgot-password');
$middleware->throttle('reset', 'reset-password');
$middleware->throttle('2fa-challenge', 'two-factor.challenge');
$middleware->throttle('2fa-recovery', 'two-factor.challenge');
$middleware->throttle('login', 'login');
```

Each limiter uses `RateLimiter::for($name, fn(Request $r) =>
Limit::perMinute(config("rate-limits.{$name}.per_min"))->by($r->ip()));`.

The two 2FA limiters differ: `2fa-recovery` is much tighter (3/min
per IP) because recovery codes are the weaker path and an
attacker hammering recovery codes is suspicious. `2fa-challenge`
covers the TOTP path (10/min) — high enough for legit users
who fat-finger, low enough to make brute force expensive.

## Observability

### Structured JSON logging

New channel `structured` in `config/logging.php`:

```php
'structured' => [
    'driver' => 'monolog',
    'level' => env('LOG_LEVEL', 'info'),
    'handler' => StreamHandler::class,
    'formatter' => JsonFormatter::class,
    'with' => ['stream' => storage_path('logs/structured.log')],
    'processors' => [RequestIdProcessor::class, PsrLogMessageProcessor::class],
],
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => env('LOG_STACK', 'single'), // was: 'single' -> change to 'single,structured' in .env.example
    ],
],
```

New `App\Logging\RequestIdProcessor` — reads `X-Request-Id` from the
request (or generates a UUIDv4 if missing), and adds it to every
log record. The `HandleInertiaRequests` middleware also passes it
to the front-end via `$page['requestId']` for traceability in
support flows.

A `Log::shareContext(['request_id' => ...])` is called from
`HandleInertiaRequests::share()` so every log line in that request
includes the ID without each call site needing to know.

### Health endpoint

Replace the default `/up` with `App\Http\Controllers\HealthController`:

```php
public function __invoke(): JsonResponse
{
    $checks = [
        'database' => $this->checkDb(),
        'queue' => $this->checkQueue(),
        'mail' => $this->checkMail(),
        'storage' => $this->checkStorage(),
    ];
    $allOk = !in_array(false, array_column($checks, 'ok'), true);
    return response()->json(
        ['status' => $allOk ? 'ok' : 'degraded', 'checks' => $checks],
        $allOk ? 200 : 503
    );
}
```

Each check is a 2-second bounded probe. `storage/logs/laravel.log`
stays as-is; the new JSON channel is separate so existing log
ingestions don't break.

### Optional: Sentry

Add the official `sentry/sentry-laravel` package. The composer
require line is `composer require sentry/sentry-laravel`. It's
only enabled if `SENTRY_LARAVEL_DSN` is set in the env — zero
behavior change when the env var is empty. **Owner work**, not
in the test plan — we add the package, document the env var,
but don't wire the DSN to anything yet.

## UX polish

### Settings/Security.vue

- Wrap the page in `LayoutAuth` (which already has the mesh canvas)
  instead of the generic AuthenticatedLayout's main content area
  (which hides the mesh). One `<div class="relative z-10">` wrapper.
- Add a "2FA challenge completed" success toast on the Challenge.vue
  page (currently a generic redirect flash; make it explicit).
- Disable button gets a 3-second countdown confirmation (you
  click "Desativar 2FA" → 3-second hold-to-confirm pattern,
  inspired by Apple "Slide to power off").
- "Trust this device" checkbox is currently below the TOTP code
  field — move it above, with a clearer label and helper text.
- Trust-device list shows `last_seen_at` in pt-BR relative
  format ("há 2 dias") via the existing `Intl` helper.

### Security.vue (the 2FA settings hub)

- Status pill: green "Ativado" / amber "Desativado" / red
  "Desativando..." (during the email-confirmation flow).
- Show "Última verificação: há X minutos" when 2FA is enabled.
- Show recovery code count "8 de 10 códigos restantes" — when
  below 3, show a "Regenerar códigos" button (out of scope for
  v0.10.0, just the UI placeholder).

## Routes / middleware changes

`bootstrap/app.php`:

- Register 6 named rate limiters.
- Alias `health` route.
- Add `throttle:verify` etc. to the 6 routes listed in the
  Rate Limiting section above.
- Explicit CSRF on `/api/*` endpoints (Laravel 13's `web` group
  adds it via `VerifyCsrfToken` middleware already; just add an
  `EnsureFrontendRequestsAreStateful` analogue or rely on
  existing session middleware + verify in CI).

`routes/web.php`:

- 6 routes get `->middleware('throttle:NAME')`.
- The 2FA challenge store route needs to be smart: it counts
  TOTP attempts and recovery attempts separately. The middleware
  throttles per IP; the controller already throttles per
  user/email. Both must be present.

## File checklist

**Backend (track 1) — observability + resilience + refactor:**

- `app/Services/Auth/BearerTokenService.php` (new)
- `app/Services/Auth/EmailVerificationService.php` (rewrite to thin adapter)
- `app/Services/Auth/PasswordResetService.php` (rewrite to thin adapter)
- `app/Services/Auth/TwoFactorEnrollmentService.php` (rewrite to thin adapter)
- `app/Logging/RequestIdProcessor.php` (new)
- `app/Http/Controllers/HealthController.php` (new)
- `app/Http/Middleware/InjectRequestId.php` (new — adds X-Request-Id to every request)
- `app/Http/Middleware/HandleInertiaRequests.php` (modify — share requestId + pass to structured logger)
- `config/logging.php` (add `structured` channel)
- `config/rate-limits.php` (new)
- `bootstrap/app.php` (register 6 limiters, alias `health`, register `InjectRequestId` middleware globally)
- `routes/web.php` (add 6 `throttle:` middleware on the auth routes)
- `routes/web.php` (alias `/up` to HealthController)
- `composer.json` (add `sentry/sentry-laravel` as optional)
- `.env.example` (add `LOG_STACK`, `RATE_LIMIT_*`, `SENTRY_LARAVEL_DSN`)
- `README.md` (update to reflect v0.9.0 reality + new polish section)

**Frontend (track 2) — UX polish:**

- `resources/js/Pages/Auth/TwoFactorChallenge.vue` (mesh canvas wrapper, trust checkbox repositioned, success toast)
- `resources/js/Pages/Auth/TwoFactorEnableConfirm.vue` (mesh canvas wrapper, better copy)
- `resources/js/Pages/Auth/TwoFactorDisableConfirm.vue` (3-second hold-to-confirm button)
- `resources/js/Pages/Settings/Security.vue` (status pill, last-seen, recovery count)
- `resources/js/Components/ConfirmHoldButton.vue` (new — reusable hold-to-confirm button)
- `resources/css/app.css` (add `.status-pill` styles if not already there)
- `resources/js/Layouts/AuthenticatedLayout.vue` (small touch — ensure mesh canvas is visible in 2FA flows)

**Tests (track 2 absorbs the testing):**

- `tests/Feature/Auth/HealthEndpointTest.php` (new — 4 cases)
- `tests/Feature/Auth/RateLimitTest.php` (new — 6 cases, one per limiter)
- `tests/Feature/Auth/BearerTokenServiceTest.php` (new — 5 cases for the base service)
- `tests/Feature/Auth/TwoFactorChallengeTest.php` (extend with 2 new cases: rate limit + brute force protection)
- All existing 261 tests must still pass (no regressions)

## Constraints / gotchas (carry-over from PR1 + PR2 + PR3)

- **Worktree per track** (lesson from FASE 5 + PR1 + PR2 + PR3): each
  parallel track uses its own `git worktree add`. Backend and
  frontend land in different branches (`feature/polish-backend`
  and `feature/polish-frontend`), then merge in that order.
- **Vendor symlink workaround** (lesson from PR2): in each worktree,
  `rm -rf vendor && cp -R /tmp/solar/vendor vendor` and set
  `APP_BASE_PATH=$(pwd)` in `.env`.
- **Reuse single source of truth** for the token table —
  `BearerTokenService` is the new single owner, the 3 existing
  services are thin wrappers. Do NOT create new token tables
  or new hash/lookup logic. The same SHA-256 hash from PR1
  is reused.
- **Don't change the `purpose` column CHECK constraint** — the
  union `('email_verification' | 'password_reset' |
  'two_factor_enroll' | 'two_factor_disable')` is final for FASE 4D.
- **Don't touch 2FA test files** (`TwoFactor*Test.php`) — the test
  track's 22 cases are the contract. The 2 new rate-limit cases
  go in a new `RateLimitTest.php` file.
- **Inertia 3.x `useForm`, not `router.X`** (lesson from PR1).
- **`Crypt::encryptString`** for TOTP secrets; SHA-256 for
  recovery codes. Bearer tokens are SHA-256 only.
- **CSRF** — the 2FA confirm POST endpoints are inside the
  `signed` group (PR3 lesson) which already implies CSRF via
  Laravel's VerifyCsrfToken. Don't add CSRF explicitly there.
- **Apple-style UI** (user preference): whitespace, soft shadows,
  liquid-crystal glass, ease-out animations. Reuse the existing
  `.glass`, `.btn-primary`, `.input-glass` tokens.

## After v0.10.0 ships

FASE 4D + polish is fully complete. Move to FASE 7 (i18n tri-língue)
in the next major. FASE 7 will touch the same forms (Category, Tag,
Settings) — having `BearerTokenService` as a single source of truth
makes the i18n changes straightforward (one place to translate
the mail templates, one place to translate the rate-limit messages).
