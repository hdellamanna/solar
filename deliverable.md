# Backend polish — FASE Polish / v0.10.0

## Summary

Shipped the FASE Polish backend track: `BearerTokenService` refactor
extracted from the three email-token services (public surface preserved
1-for-1), 6 named rate limiters wired into the auth flow routes,
`X-Request-Id` middleware + structured JSON log channel,
`/up` health probe with 4 bounded subsystem checks, optional
`sentry/sentry-laravel` integration, and the v0.10.0 README +
`.env.example` updates. All 261 existing tests pass without
modification.

## Branch

- URL: https://github.com/hdellamanna/solar/tree/feature/polish-backend
- Worktree: `/tmp/solar-polish-backend`
- Base: `main` @ `ddf24f2`
- Tip: `26fa6f7955badf0ce3fc91bfd2ad35020c8a5ae3`
- Commits: 5 logical commits (refactor → rate limiting → observability → health → docs)

## Commit hashes

```
26fa6f7 docs: README v0.10.0 status + observability section; .env.example polish keys; sentry-laravel
ff814e4 feat(health): bounded /up health probe with 4 subsystem checks
100326c feat(observability): X-Request-Id middleware + structured JSON log channel
03ede09 feat(auth): per-IP rate limit on every auth flow + /up health route
2664f7b refactor(auth): extract BearerTokenService, rewrite 3 adapters as thin wrappers
```

## Test result

```
$ php artisan test
Tests:  261 passed (2248 assertions)
Duration: 2.5s
```

**261 / 261 green.** The 47 auth tests (12 EmailVerification, 13
PasswordReset, 22 TwoFactor) all pass without modification — the
public surface of the 3 rewritten services is byte-for-byte
equivalent to the pre-refactor implementation.

## Files created

| Path | Purpose |
|---|---|
| `app/Services/Auth/BearerTokenService.php` | Single owner of the token lifecycle (mint / consume / throttle). Adapters pick the purpose, meta payload, and post-consume handler. |
| `app/Services/Auth/InvalidTokenException.php` | Generic token exception. Adapters wrap in their purpose-specific subclass to keep the controller error-routing contract. |
| `app/Logging/RequestIdProcessor.php` | Monolog processor that attaches the request id to every record's `extra` array. |
| `app/Http/Middleware/InjectRequestId.php` | Reads / generates the `X-Request-Id` header, stashes it on the request, calls `Log::shareContext`, echoes it on the response. |
| `app/Http/Controllers/HealthController.php` | 4 bounded probes (database, queue, mail, storage) + 200 / 503 JSON response. |
| `config/rate-limits.php` | 6 named limiters, all values env-overridable. |

## Files modified

| Path | Change |
|---|---|
| `app/Services/Auth/EmailVerificationService.php` | Thin adapter over BearerTokenService. Public surface preserved. |
| `app/Services/Auth/PasswordResetService.php` | Thin adapter over BearerTokenService. Public surface preserved. |
| `app/Services/Auth/TwoFactorEnrollmentService.php` | Thin adapter over BearerTokenService. `confirmEnable` / `confirmDisable` run their post-consume action inside the `consume()` closure. |
| `app/Http/Middleware/HandleInertiaRequests.php` | Adds `requestId` to the shared Inertia props. |
| `app/Providers/AppServiceProvider.php` | Registers the 6 auth limiters + the default `api` limiter. |
| `config/logging.php` | Adds the `structured` Monolog channel (JSON formatter + RequestIdProcessor). |
| `bootstrap/app.php` | Prepends `InjectRequestId` to the web group; calls `throttleApi()`. |
| `routes/web.php` | Adds `/up` health route + `throttle:NAME` middleware on 5 auth routes. |
| `composer.json` / `composer.lock` | Adds `sentry/sentry-laravel:^4.0`. |
| `.env.example` | Adds `LOG_STACK=single,structured`, `LOG_REQUEST_HEADER`, all 6 `RATE_LIMIT_*` keys, `SENTRY_LARAVEL_DSN=`. |
| `README.md` | Updates "Current status" to v0.10.0; adds the "Observability & resilience" section; refreshes the test result line; updates the FASE 10 roadmap row. |

## Notes for the verifier

### Backward compatibility — the critical bit

The existing 261 tests poke the throttle cache keys DIRECTLY in a few
cases to assert the throttle works. The BearerTokenService refactor
preserves those exact key shapes so the tests pass without
modification:

- `email_verification:last_sent:{userId}` (EmailVerificationTest #7)
- `email_verification:hourly_count:{userId}` (EmailVerificationTest #8)
- `password-reset:throttle:{emailHash}:last_sent` (PasswordResetTest #3)
- `password-reset:throttle:{emailHash}:hourly_count` (PasswordResetTest #4)

BearerTokenService exposes two flavours of the throttle API to keep
these keys stable:

- `canResend(string $email, string $purpose): bool` for the
  email-hash-keyed flows (password_reset, 2FA)
- `canResendForUser(User $user): bool` for the email_verification
  flow (keyed by user id, because the legacy service was too)

### The `2fa-recovery` 3/min cap

The design calls for two 2FA limiters — `two-factor.challenge`
(10/min, TOTP path) and `two-factor.recovery` (3/min, recovery
path). The route is a single POST, so the two throttles are
stacked. The first cap to fire wins, which means a recovery
attempt is effectively bounded at 3/min while a TOTP-only user
gets the roomier 10/min. The service-layer per-user counter in
`TwoFactorService` is unchanged, so a single attacker hopping IPs
is still bounded per user.

### The `throttleApi()` 60/min baseline

I added `RateLimiter::for('api', ...)` registration in
`AppServiceProvider::boot()` because the `$middleware->throttleApi()`
call in `bootstrap/app.php` requires a named limiter named `api`
to exist — without it the `/api/*` routes 500 with
`MissingRateLimiterException`. The `api` limiter is 60/min per IP
(the framework default).

### /up is registered in `routes/web.php`, not via `health: '/up'`

The framework's `withRouting(health: '/up')` registers a default
stub INSIDE `buildRoutingCallback` that runs BEFORE the web group
loads. A custom route in the web group is later in the
`RouteCollection` and so never wins the match. The only clean
override is to register the route in `routes/web.php` as the first
route in the file. The `health` named route alias is preserved.

### Worktree setup quirk

The brief said `cp -R /tmp/solar/vendor vendor` but on this
machine there was no pre-existing `vendor` symlink in
`/tmp/solar` to remove. The plain `cp -R` worked as-is.

The brief also said set `APP_BASE_PATH=$(pwd)` in `.env`. I did
that but it didn't actually need to be set — the resolved base
path was correct without it. Left in for safety.

I also had to `cp -R /tmp/solar/public/build /tmp/solar-polish-backend/public/build`
because Vite's `public/build/manifest.json` is gitignored but the
dashboard tests need it. Without it, ~10 tests fail with
`ViteManifestNotFoundException`. Not a refactor regression.

### Composer install vs `composer require`

I ran `composer require sentry/sentry-laravel:^4.0 --no-interaction`
to add the dependency. It worked because the worktree's vendor
was a real copy of the main repo's vendor. The brief said
`composer install` times out — `composer require` of a single
package is much faster (just resolves + downloads the new package
and its transitive deps).

### No CSRF changes

The brief mentioned "Explicit CSRF on API routes" as a goal. The
existing `api` middleware group in `bootstrap/app.php` already
includes `VerifyCsrfToken` (I verified the registration), so
explicit CSRF was a no-op. Left the file untouched in that
respect.

### The 2 2FA limiters in `config/rate-limits.php`

`two-factor.challenge` (10/min) and `two-factor.recovery` (3/min)
are both registered. The design's "6 named limiters" requirement
is satisfied even though only one POST route consumes both —
the test track can write 6 RateLimitTest cases, one per
limiter name.
