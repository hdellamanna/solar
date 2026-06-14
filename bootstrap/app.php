<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InjectRequestId;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        // FASE Polish / v0.10.0 — register the bounded health
        // check in `routes/web.php` as the first route so it
        // shadows the default Laravel stub. The default stub
        // returns 200 with no body and a 500 on
        // `DiagnosingHealth` exceptions; the new controller
        // returns 200/503 + per-subsystem status.
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Inject the X-Request-Id BEFORE Inertia so the id is
        // already in Monolog's shared context when the
        // HandleInertiaRequests middleware runs and so the
        // shared Inertia props carry the id on the first
        // render of the page. Also before every other
        // middleware that might log.
        $middleware->web(prepend: [
            InjectRequestId::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // 60/min baseline on every API route. Per-endpoint
        // tighter caps are bound to named limiters in
        // App\Providers\AppServiceProvider and applied via
        // `throttle:NAME` in routes/api.php / web.php.
        $middleware->throttleApi();

        // Aliases. The `verified` alias is the Laravel convention and
        // is what `routes/web.php` uses to gate authenticated routes
        // behind a confirmed email address (FASE 4D / Auth Phase 1).
        // `two_factor` is the Auth Phase 3 equivalent for the 2FA
        // challenge.
        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'two_factor' => EnsureTwoFactorVerified::class,
        ]);

        // The /api/* endpoints are called from the authenticated Vue front-end
        // (autocomplete, search, etc.) and need access to the session cookie.
        // Laravel 13's `api` group is stateless by default — replace it with
        // the `web` group so requests carry the session and CSRF token.
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('up'),
        );
    })->create();
