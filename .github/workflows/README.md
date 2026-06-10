# CI / CD

This directory hosts the GitHub Actions workflows for Solar.

## `ci.yml`

Runs on every push to `main` and every pull request targeting `main`.

The job boots an Ubuntu runner, installs PHP 8.4 with the extensions
Laravel needs (mbstring, dom, fileinfo, sqlite, pdo_sqlite, bcmath,
intl, zip, curl), then Node 22. Both Composer and npm dependencies are
cached based on lockfile hashes. After `composer install` and `npm ci`,
it copies `.env.example` → `.env`, generates an `APP_KEY`, builds the
Vite bundle, creates the SQLite database, runs `migrate:fresh --seed`,
and finally executes `php artisan test`.

In-progress runs of the same branch are cancelled when a new commit
lands (concurrency group `ci-${{ github.ref }}`), saving CI minutes
on rapid pushes.

## `dependabot.yml`

Weekly checks for Composer (`composer.lock`) and npm (`package-lock.json`)
updates, monthly checks for GitHub Actions. Dependabot opens up to 5
PRs per ecosystem per week, all labelled `dependencies` (plus the
ecosystem-specific tag), with commit prefixes (`composer:`, `npm:`,
`ci:`) that keep history readable.
