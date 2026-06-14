<?php

namespace App\Providers;

use App\Services\UserMotionPreference;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // FASE 4D — singleton that resolves the effective motion
        // level (auto/reduced/full) by combining the user's stored
        // preference with the OS `prefers-reduced-motion` signal.
        $this->app->singleton(UserMotionPreference::class, function ($app) {
            return new UserMotionPreference($app['request']->user());
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerDefaultApiRateLimiter();
        $this->registerAuthRateLimiters();
    }

    /**
     * Register the default `api` rate limiter that the
     * `$middleware->throttleApi()` call enables on the `api`
     * middleware group. 60/min per IP is the framework
     * default — endpoints that need a tighter cap layer a
     * `throttle:NAME` middleware on top of the group.
     */
    private function registerDefaultApiRateLimiter(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip());
        });
    }

    /**
     * Register the named rate limiters consumed by the
     * `throttle:NAME` middleware aliases in routes/web.php.
     *
     * Each limiter is per-IP (the `by($r->ip())` clause) and
     * per-minute (the `perMinute(...)` clause). The numeric
     * value is read from `config/rate-limits.php` so an
     * operator can bump a limit without redeploying.
     *
     *  - `login`               : POST /login
     *  - `verify`              : POST /email/verify/resend
     *  - `forgot-password`     : POST /forgot-password
     *  - `reset-password`      : POST /reset-password
     *  - `two-factor.challenge`: POST /two-factor/challenge
     *                           (TOTP path)
     *  - `two-factor.recovery` : POST /two-factor/challenge
     *                           (recovery-code path)
     *
     * The 2FA challenge route is mapped to TWO limiters by the
     * controller (TOTP and recovery-code counters) so the
     * recovery path can be tighter than the TOTP path even
     * though both hit the same URL.
     *
     * Note on key lookup: two of the config keys (`two-factor.challenge`
     * and `two-factor.recovery`) contain a dot, which the
     * `config()` helper interprets as a nested path separator.
     * Reading with `config("rate-limits.two-factor.recovery.per_min")`
     * returns NULL because Laravel looks for the literal
     * `two-factor` > `recovery` > `per_min` chain under
     * `rate-limits` — but the config file stores them as a
     * SINGLE key with a dotted name. We use array access with
     * the literal key to keep the dotted name intact.
     */
    private function registerAuthRateLimiters(): void
    {
        $register = function (string $name, string $configKey): void {
            RateLimiter::for($name, function (Request $request) use ($configKey): Limit {
                $config = config('rate-limits');
                $perMinute = (int) ($config[$configKey]['per_min'] ?? 10);

                return Limit::perMinute($perMinute)->by($request->ip());
            });
        };

        $register('login', 'login');
        $register('verify', 'verify');
        $register('forgot-password', 'forgot-password');
        $register('reset-password', 'reset-password');
        $register('two-factor.challenge', 'two-factor.challenge');
        $register('two-factor.recovery', 'two-factor.recovery');
    }
}
