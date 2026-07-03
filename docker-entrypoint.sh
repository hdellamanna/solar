#!/usr/bin/env bash
#
# Solar Money — container entrypoint.
#
# Runs every time the Docker container starts (and on every Render redeploy).
# Idempotent: safe to run multiple times.
#
# Steps:
#   1. Wait for the database to be reachable (Render Postgres has a 5-30s boot delay).
#   2. Run migrations.
#   3. Run the seed (idempotent — uses firstOrCreate + email_verification_tokens).
#   4. Cache config / routes / views.
#   5. Create the storage symlink (idempotent: php artisan storage:link returns non-zero if exists).
#   6. Warm the OPcache (no-op, but clears any stale entries from a previous deploy).
#   7. exec the CMD (supervisord) — Docker best practice so PID 1 = supervisord.

set -e
set -o pipefail

cd /app

log() { echo "[entrypoint] $*" >&2; }

# ── 1. Wait for database ─────────────────────────────────────────────────
if [[ -n "${DATABASE_URL:-}" ]]; then
    log "Waiting for database (DATABASE_URL set)..."
    # Render Postgres boots in 5-30s. Loop until `pg_isready` succeeds or 60s elapses.
    for i in {1..30}; do
        # Parse DATABASE_URL=postgres://user:pass@host:port/db
        if [[ "$DATABASE_URL" =~ postgres(ql)?://([^:]+):([^@]+)@([^:]+):([0-9]+)/(.+) ]]; then
            host="${BASH_REMATCH[4]}"
            port="${BASH_REMATCH[5]}"
        else
            log "DATABASE_URL not parseable; skipping wait."
            break
        fi

        if nc -z "$host" "$port" 2>/dev/null; then
            log "Database is reachable on ${host}:${port}."
            break
        fi

        log "Waiting for database on ${host}:${port}... ($i/30)"
        sleep 2
    done
fi

# ── 2. Migrations ───────────────────────────────────────────────────────
log "Running migrations..."
php artisan migrate --force --no-interaction

# ── 2b. Fix storage ownership after migrate/seed ────────────────────────
# The entrypoint runs as root but php-fpm (which serves requests) runs as
# www-data. If DB_CONNECTION=sqlite, `migrate` creates the .sqlite file as
# root and www-data can't write to it on the first request — so /up returns
# a 500 with "attempt to write a readonly database". We chown the entire
# storage tree so www-data can read+write everything it needs. This is a
# no-op for Postgres deployments (no sqlite file) but harmless either way.
chown -R www-data:www-data /app/storage 2>/dev/null || true
chmod -R ug+rwX /app/storage 2>/dev/null || true

# ── 3. Seed (idempotent) ────────────────────────────────────────────────
# The DatabaseSeeder uses firstOrCreate everywhere, so re-running on
# every boot is safe. Skip if explicitly disabled via SEED_ON_BOOT=false.
if [[ "${SEED_ON_BOOT:-true}" == "true" ]]; then
    log "Seeding database (idempotent)..."
    php artisan db:seed --force --no-interaction
else
    log "SEED_ON_BOOT=false; skipping seed."
fi

# ── 4. Cache config / routes / views ────────────────────────────────────
# These are idempotent — they overwrite the existing cache files.
log "Caching config, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── 5. Storage symlink ──────────────────────────────────────────────────
# `storage:link` exits non-zero if the link already exists, which is fine —
# we suppress the error and move on.
log "Ensuring storage symlink..."
php artisan storage:link 2>&1 || true

# ── 6. Mark setup as complete (skip the wizard) ─────────────────────────
# The wizard writes setup_completed_at; the entrypoint also marks it
# complete so a fresh deploy doesn't force the operator through the wizard
# again. To re-run the wizard, set MARK_SETUP_COMPLETE=false and delete
# the app_meta row.
if [[ "${MARK_SETUP_COMPLETE:-true}" == "true" ]]; then
    php artisan tinker --execute="
        \$exists = \App\Models\AppMeta::where('key', 'setup_completed_at')->exists();
        if (! \$exists) {
            \App\Models\AppMeta::create(['key' => 'setup_completed_at', 'value' => (string) now()->toIso8601String()]);
            echo '[entrypoint] setup_completed_at marked.';
        }
    " 2>/dev/null || true
fi

# ── 7. Optimise autoloader (in case composer dump missed anything) ─────
log "Optimising autoloader..."
composer dump-autoload --optimize --no-dev --no-interaction 2>&1 || true

# ── 8. Hand off to the CMD (supervisord) ─────────────────────────────────
log "All done. Handing off to: $*"
exec "$@"