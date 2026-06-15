<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Unit coverage for the SetLocale
 * middleware (the single source of truth for "what locale is
 * active for this request").
 *
 * The middleware's resolution order, per the class docblock, is:
 *
 *   1. Authenticated user (`auth()->user()->locale`)
 *   2. `X-App-Locale` request header
 *   3. `app_locale` cookie
 *   4. `config('app.locale')` (the default, pt-BR)
 *
 * The 5 cases below cover the happy paths and the negative
 * path: a candidate locale outside the whitelist (e.g. a stale
 * cookie set to "fr-FR") is silently coerced back to the
 * default.
 *
 * The middleware is invoked directly via `->handle()` with a
 * closure that returns a minimal response. We assert on
 * `app()->getLocale()` (the contract every controller and
 * mailer downstream relies on) instead of poking at internal
 * state.
 */
class SetLocaleTest extends TestCase
{
    /**
     * Flush the array cache so the throttle counters the
     * middleware does not write do not leak between cases.
     * (The middleware does not currently use the cache, but
     * we flush anyway to keep this test file stable if the
     * implementation adds a `Cache::remember` later.)
     */
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Invoke the middleware against a request and return
     * whatever the inner closure returned (a 200 response is
     * plenty — the middleware's job is to mutate the
     * application's locale, not the response).
     */
    private function runMiddleware(Request $request): void
    {
        $middleware = new SetLocale();
        $middleware->handle($request, fn () => response('ok', 200));
    }

    public function test_resolves_user_locale_when_authenticated(): void
    {
        // We use a plain Eloquent-skipping stub for the user:
        // the middleware only ever calls `->locale` on it,
        // so a stdClass with the right property is enough
        // and we can keep this test in the `Unit` suite
        // (no schema migration needed).
        $user = new class {
            public string $locale = 'es';
        };

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->runMiddleware($request);

        $this->assertSame('es', app()->getLocale());
    }

    public function test_resolves_header_when_guest(): void
    {
        // Guest (no actingAs). The X-App-Locale header is
        // the canonical front-end signal after a locale
        // switch — see Settings/LocaleController.
        $request = Request::create('/about', 'GET');
        $request->headers->set('X-App-Locale', 'en');

        $this->runMiddleware($request);

        $this->assertSame('en', app()->getLocale());
    }

    public function test_resolves_cookie_when_guest_and_no_header(): void
    {
        // Guest, no header, but a fresh cookie the user
        // set on a previous visit. The SetLocale
        // middleware reads the cookie via
        // `$request->cookie('app_locale')` which the
        // Request factory populates when you pass a
        // cookies array.
        $request = Request::create('/about', 'GET', [], [
            'app_locale' => 'es',
        ]);

        $this->runMiddleware($request);

        $this->assertSame('es', app()->getLocale());
    }

    public function test_falls_back_to_config_locale_when_no_signal(): void
    {
        // Guest, no header, no cookie — the middleware
        // must fall through to `config('app.locale')`
        // which is `pt-BR` in this worktree's .env.
        $request = Request::create('/about', 'GET');

        $this->runMiddleware($request);

        $this->assertSame(config('app.locale'), app()->getLocale());
        $this->assertSame('pt-BR', app()->getLocale());
    }

    public function test_invalid_locale_falls_back_to_pt_br(): void
    {
        // A malicious or stale cookie set to a locale
        // outside `config('app.available_locales')`
        // (pt-BR / es / en) must NOT leak through — the
        // middleware silently coerces it to the default
        // (pt-BR). This is the security guardrail the
        // middleware's class docblock calls out.
        $request = Request::create('/about', 'GET', [], [
            'app_locale' => 'fr-FR',
        ]);

        $this->runMiddleware($request);

        $this->assertSame('pt-BR', app()->getLocale());
    }
}
