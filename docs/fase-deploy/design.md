# FASE Deploy — production deployment guide for Solar Money

**Target release:** v0.13.0
**Goal:** turn Solar Money into a 1-click deployable web app on **Render.com** (free tier or $7/mo paid), with a `render.yaml` Blueprint that creates the entire infra (web service + Postgres + cron) and a Dockerfile that runs the Laravel app with PHP-FPM + Nginx. Bonus: a `first-boot wizard` that surfaces every required env var on the very first visit so the operator knows exactly what to set.

## Motivation

Solar is currently only runnable from a `git clone` + `composer install` + `npm install` on a local machine. That works for development but blocks demos, shareable URLs, and any kind of production deployment. Cloudflare Pages Free was the first ask — but Cloudflare Pages only serves static assets, not Laravel. **Render.com** is the simplest free hosting that supports PHP + Postgres + persistent storage, with a generous free tier (750 hours/month, sleeps after 15min inactivity) and a $7/mo paid tier that never sleeps.

This FASE ships:
1. A `render.yaml` Blueprint (infra-as-code: web service + Postgres + cron)
2. A production-grade `Dockerfile` (PHP 8.4-FPM + Nginx + Node 22 for the build stage)
3. A `docker-entrypoint.sh` that runs `migrate` + `config:cache` + `route:cache` + `view:cache` + `storage:link` on every container boot (idempotent)
4. A `Procfile` (Render fallback if Dockerfile is rejected for any reason)
5. A `README > Production deployment` section with a **3-step** Render deploy walkthrough
6. A **first-boot wizard** at `/setup` that, on the very first visit when `APP_KEY` is empty OR the seeder hasn't run, lists every required env var and walks the operator through pasting them in. The wizard is itself an Inertia page — no JS frameworks, just a `useForm` POST loop.

The FASE does **not** ship the OAuth providers (FASE 8) — it ships the *envelope* (the env vars the wizard lists) so that when OAuth lands, it's a 1-line `GOOGLE_CLIENT_ID=` in the Render dashboard and nothing else.

## Storage

No schema changes. This FASE is pure infra + docs + a single new Inertia page (`/setup`).

The wizard needs a marker for "first boot" — the cheapest is to check `AppMeta::get('setup_completed_at')`. If `null`, redirect `/dashboard` and `/` to `/setup`. After the operator completes the wizard, set `setup_completed_at = now()`.

## Middleware

### `App\Http\Middleware\RequireSetup`

Runs after `SetLocale`, before `auth`. Checks `app_meta.setup_completed_at`. If `null`:
- If request is to `/setup` or `/setup/*`: pass through
- If request is to `/up` (health check) or `/assets/*`: pass through
- Otherwise: 302 to `/setup`

The middleware is **idempotent and reversible** — the operator can `DELETE FROM app_meta WHERE key = 'setup_completed_at'` to re-trigger the wizard if they want to re-audit their env vars.

## Routes

New routes inside the existing `web` group, OUTSIDE the `auth` middleware (so the operator can hit them before being logged in):

```php
Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');
Route::post('/setup/skip', [SetupController::class, 'skip'])->name('setup.skip');
```

`POST /setup` validates the env vars and writes them to `app_meta` (only the ones the wizard collected). It does NOT modify the `.env` file directly (Render-managed env vars can't be edited from inside the app anyway).

`POST /setup/skip` writes `setup_completed_at = now()` and redirects to `/login`. The skip is for the case where the operator already has everything configured manually in the Render dashboard.

## Controller

`App\Http\Controllers\SetupController`:

- `show()` — renders `Setup/Index.vue` with a list of every env var the wizard knows about (DB, mail, queue, cache, session, APP_KEY rotation status, optional OAuth client_id/secret placeholders for future providers). Each env var has: label, description, current value (masked for secrets), `is_set` boolean, and a `required` boolean.
- `store(Request $r)` — validates the form, runs the **validation pass** (calls `Artisan::call('migrate', ['--force' => true])`, then `db:seed --class=DatabaseSeeder`, then hits `/up` to confirm). On success: writes `app_meta.setup_completed_at = now()` and redirects to `/login` with a flash message "Setup complete. Demo user: demo@solar.app / solar123".
- `skip()` — writes `app_meta.setup_completed_at = now()` and redirects to `/login`.

The `store()` method NEVER persists secrets to `app_meta` (it stores only non-secret metadata like `setup_completed_at` and a `mail_configured_at` flag). The actual env vars live in Render's env-var store.

## Vue page

`resources/js/Pages/Setup/Index.vue`:

- Top: a "Solar Money setup" header + a 1-line description
- 6 collapsible sections: **App**, **Database**, **Mail**, **Cache / Queue / Session**, **Optional integrations (OAuth — coming soon)**, **Health check**
- Each section: a card with the env var name, current value (masked for secrets), status badge (✓ configured / ✗ missing / ⚠ not recommended), and an inline edit input
- Bottom: a "Save & finish" button + a "Skip for now" button
- After save: a 3-second success toast and a redirect to `/login`

The page does NOT use any heavy JS framework — it's `useForm` + Inertia standard patterns, no vue-i18n needed (the wizard is a one-time setup screen that always renders in the operator's locale, default pt-BR).

## Render Blueprint (`render.yaml`)

```yaml
services:
  - type: web
    name: solar-money
    runtime: docker
    plan: free          # change to "starter" ($7/mo) to disable sleep
    dockerfilePath: ./Dockerfile
    dockerContext: .
    healthCheckPath: /up
    autoDeploy: true
    envVars:
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: false
      - key: APP_URL
        fromService: { type: web, name: solar-money, property: hostUrl }
      - key: APP_KEY
        generateValue: true       # Render generates a base64:... value
      - key: LOG_CHANNEL
        value: stack
      - key: LOG_STACK
        value: single,structured
      - key: DB_CONNECTION
        fromService: { type: pserv, name: solar-db, property: databaseConnectionString }
      - key: SESSION_DRIVER
        value: database
      - key: CACHE_STORE
        value: database
      - key: QUEUE_CONNECTION
        value: database
      - key: FILESYSTEM_DISK
        value: local
      - key: MAIL_MAILER
        value: resend
      - key: MAIL_FROM_ADDRESS
        value: noreply@solar.example
      - key: RESEND_API_KEY
        sync: false              # user sets in Render dashboard; wizard reminds them
      - key: APP_LOCALE
        value: pt-BR
      - key: APP_FALLBACK_LOCALE
        value: en
      - key: APP_FAKER_LOCALE
        value: pt_BR
      - key: TRUSTED_PROXIES
        value: '*'                # Render sits behind a load balancer
      - key: SENTRY_LARAVEL_DSN
        sync: false
  - type: pserv
    name: solar-db
    plan: free          # change to "starter" ($7/mo) for 7-day backups
    env: docker
    ipAllowList: []     # only allow same-VPC access; the web service uses DATABASE_URL
```

## Dockerfile (multi-stage)

```dockerfile
# Stage 1 — build assets
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# Stage 2 — PHP deps
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Stage 3 — runtime
FROM php:8.4-fpm-alpine
RUN apk add --no-cache nginx supervisor curl bash icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite intl zip bcmath opcache
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
EXPOSE 10000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
```

Render auto-detects port 10000 as the public port.

## Test plan

### Unit

- `tests/Unit/Middleware/RequireSetupTest.php` (4 cases):
  - test_redirects_to_setup_when_setup_completed_at_is_null
  - test_passes_through_setup_routes_when_setup_completed_at_is_null
  - test_passes_through_health_route_when_setup_completed_at_is_null
  - test_passes_through_all_routes_when_setup_completed_at_is_set

- `tests/Unit/Services/SetupValidatorTest.php` (5 cases):
  - test_detects_missing_app_key
  - test_detects_invalid_database_connection
  - test_detects_missing_mail_driver_config
  - test_passes_when_all_required_vars_set
  - test_masks_secrets_in_reported_state

### Feature

- `tests/Feature/Setup/SetupWizardTest.php` (4 cases):
  - test_setup_wizard_is_reachable_when_setup_incomplete
  - test_setup_wizard_skips_authentication
  - test_post_setup_writes_setup_completed_at_and_redirects
  - test_setup_skip_writes_setup_completed_at_and_redirects

- `tests/Feature/Setup/SetupMiddlewareTest.php` (3 cases):
  - test_dashboard_redirects_to_setup_when_setup_incomplete
  - test_dashboard_passes_through_after_setup_complete
  - test_setup_route_passes_through_in_both_states

### CI

- New GitHub Actions workflow `.github/workflows/render-deploy-preview.yml` that, on PR open, triggers a Render preview deploy via the Render Deploy Hook API. PRs get a "View deploy" comment auto-posted with the preview URL. Skipped if the repo doesn't have a Render API key configured (Render API key is a repo secret, only set after the first Render deploy).

## Out of scope

- OAuth providers (FASE 8) — wizard mentions `GOOGLE_CLIENT_ID` etc as placeholders but does not implement them
- Cron / scheduled jobs (Render cron service) — added in FASE 9 when there's something to schedule
- CDN / Cloudflare in front of Render — FASE 10
- Email reputation / DKIM / SPF — the README links to Resend's docs; the operator configures it once

## Migration notes

- No DB schema changes
- No breaking changes to public API
- Operator must:
  1. Fork the repo to their GitHub
  2. Sign up at render.com
  3. Click "New > Blueprint" > point to their fork
  4. Render creates the Postgres + web service from `render.yaml`
  5. Set the secrets (RESEND_API_KEY, SENTRY_LARAVEL_DSN) in the Render dashboard
  6. Visit the rendered URL — first-boot wizard runs, sets `setup_completed_at`, redirects to `/login`
  7. Sign in as `demo@solar.app` / `solar123` (the seeder ran during the wizard)

## Files added

- `render.yaml` — Render Blueprint
- `Dockerfile` — multi-stage production image
- `.dockerignore`
- `docker/nginx.conf` — Render-compatible nginx (port 10000, sends .php to php-fpm via FastCGI)
- `docker/supervisord.conf` — runs nginx + php-fpm
- `docker-entrypoint.sh` — runs migrations, caches config/routes/views, then execs supervisord

## Files modified

- `routes/web.php` — adds 3 setup routes inside the `web` group OUTSIDE the `auth` group
- `bootstrap/app.php` — prepends `RequireSetup` middleware after `SetLocale`
- `app/Http/Controllers/SetupController.php` (new)
- `app/Http/Middleware/RequireSetup.php` (new)
- `app/Services/SetupValidator.php` (new) — collects current env var state + computes `is_set` per known var
- `app/Http/Controllers/SettingsController.php` — adds a "Run setup wizard again" link
- `resources/js/Pages/Setup/Index.vue` (new)
- `README.md` — adds `## Production deployment` section with the 3-step Render walkthrough

## Estimated LOC

- 4 new PHP files: ~350 LOC
- 2 new Vue files: ~250 LOC
- 4 new test files: ~400 LOC
- 4 new infra files (Dockerfile, nginx, supervisord, entrypoint): ~100 LOC
- render.yaml: ~50 LOC
- README additions: ~100 LOC

Total: ~1250 LOC across ~14 files.

## Implementation tracks (mavis-team plan)

The work splits cleanly into 2 parallel tracks because the files they touch don't overlap:

1. **`deploy-infra`** — render.yaml, Dockerfile, docker/, docker-entrypoint.sh, .dockerignore, README production-deploy section, .github/workflows/render-deploy-preview.yml. No PHP or Vue.
2. **`deploy-app`** — SetupController, RequireSetup middleware, SetupValidator, routes/web.php, bootstrap/app.php, SettingsController link, Setup/Index.vue page, all 4 test files.

The verifier for `deploy-infra` runs `docker build .` to confirm the Dockerfile builds (in <3min on Render's free tier CI runner, or on the worker's machine). The verifier for `deploy-app` runs `php artisan test` and asserts all new tests pass.

A 3rd **`deploy-docs`** track is NOT needed — the README additions are small enough to fold into `deploy-infra`.