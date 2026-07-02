<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResendVerificationController;
use App\Http\Controllers\Auth\TrustedDeviceController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorDisableController;
use App\Http\Controllers\Auth\TwoFactorEnableController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\PixController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurrenceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\LocaleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Setup\SetupController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TutorialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health check (FASE Polish / v0.10.0)
|--------------------------------------------------------------------------
|
| Bounded health probe for uptime monitors. 200 when every
| subsystem (database, queue, mail, storage) is green, 503
| when any probe fails. Registered at the top of the file
| so it shadows the default Laravel `/up` stub. Lives in
| the `web` group (session + CSRF + Inertia all run), but
| the route is GET-only with no auth, so a load balancer
| can still hit it without credentials.
*/
Route::get('/up', HealthController::class)->name('health');

/*
|--------------------------------------------------------------------------
| Root redirect
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| First-boot setup wizard (FASE Deploy)
|--------------------------------------------------------------------------
|
| Reachable when app_meta.setup_completed_at is null. The RequireSetup
| middleware redirects every other request here until the operator
| completes the wizard. Lives outside the `guest` group because the
| operator is not logged in yet.
|
*/
Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');
Route::post('/setup/skip', [SetupController::class, 'skip'])->name('setup.skip');

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    // FASE Polish / v0.10.0 — per-IP login throttle (default
    // 10/min via the `login` named limiter). Credential-
    // stuffing defence: a real user fat-fingers a few times
    // and stops, a bot pounds a dictionary and gets 429s.
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    // Password reset (FASE 4D / Auth Phase 2). Anyone — even an
    // unauthenticated visitor — can hit these.
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    // FASE Polish / v0.10.0 — per-IP forgot-password throttle
    // (default 5/min). Stops an enumeration script from
    // driving load on the mailer.
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email')
        ->middleware('throttle:forgot-password');

// The GET form route deliberately does NOT use the `signed`
// middleware: the controller does the validity check itself and
// bounces the user back to forgot-password with a friendly
// error flash (the design's "bad token" UX). The signature on
// the URL still gives us a hard 60-minute TTL at the database
// level via the token's `expires_at`.
Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
    ->name('password.reset');

/*
|--------------------------------------------------------------------------
| 2FA confirmation GETs + POSTs (FASE 4D / Auth Phase 3)
|--------------------------------------------------------------------------
|
| The two GETs render the confirmation page after the user
| clicks the email link. The two POSTs finalise the action
| (enable: 6-digit TOTP code; disable: re-typed password).
| They live OUTSIDE the `auth` middleware because the user
| mid-enrollment may be on a different device with no active
| session. The `signed` middleware validates the temporary
| signature baked into the URL by `URL::temporarySignedRoute`;
| without an active signature, Laravel aborts with 403 before
| the controller runs. The controller does its own
| token-validity check on top of that for the "expired" /
| "already used" UX.
*/
Route::middleware('signed')->group(function () {
    Route::get('/two-factor/enable/confirm/{token}', [TwoFactorEnableController::class, 'confirmEnable'])
        ->name('two-factor.enable.confirm');
    Route::get('/two-factor/disable/confirm/{token}', [TwoFactorDisableController::class, 'confirmDisable'])
        ->name('two-factor.disable.confirm');
});

// The two POST confirmation endpoints (final action) live OUTSIDE
// the `signed` middleware group on purpose: the `token` field in
// the POST body is the credential (controller does its own
// purpose + consumed + expiry check). Putting them in the
// `signed` group would reject every legitimate POST because the
// body is not part of the URL signature.
Route::post('/two-factor/enable/confirm', [TwoFactorEnableController::class, 'confirmEnableStore'])
    ->name('two-factor.enable.store');
Route::post('/two-factor/disable/confirm', [TwoFactorDisableController::class, 'confirmDisableStore'])
    ->name('two-factor.disable.store');
});

/*
|--------------------------------------------------------------------------
| Password update (FASE 4D / Auth Phase 2)
|--------------------------------------------------------------------------
|
| Deliberately OUTSIDE the `guest` middleware group: a successful reset
| auto-logs the user in (`NewPasswordController::store` calls
| `Auth::login()`), and the token is single-use. A second POST with the
| same token must reach the controller so it can throw
| `InvalidResetTokenException` and surface the design-doc "bad token"
| UX (bounce back to forgot-password with a friendly flash). The
| `RedirectIfAuthenticated` middleware on the `guest` group would
| otherwise short-circuit the second POST to /dashboard before the
| service-layer replay check runs.
*/
Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->name('password.update')
    // FASE Polish / v0.10.0 — per-IP reset-password throttle
    // (default 5/min). Token-based: a leaked token + a
    // throttled IP can't burn the user's account. Pairs with
    // the service-layer single-use / 60-minute TTL.
    ->middleware('throttle:reset-password');

/*
|--------------------------------------------------------------------------
| Email verification callback (FASE 4D)
|--------------------------------------------------------------------------
|
| Lives outside the `auth` middleware group because the user may open
| the link in a different browser (or after their session expired).
| The `signed` middleware validates the temporary signature baked
| into the URL by `URL::temporarySignedRoute`; the controller logs
| the verified user in if no session is active.
*/
Route::get('/email/verify/{token}', [VerifyEmailController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');

    // Email verification flow (FASE 4D / Auth Phase 1).
    // Reachable by an authenticated-but-unverified user — the
    // `EnsureEmailIsVerified` middleware exempts these named routes
    // explicitly. The verify route lives OUTSIDE the auth group so a
    // user can open the link in a different browser and still have
    // it work (the controller logs the verified user in).
    Route::get('/email/verify', [VerifyEmailController::class, 'notice'])->name('verification.notice');
    // FASE Polish / v0.10.0 — per-IP verify-resend throttle
    // (default 10/min via the `verify` named limiter). The
    // service-layer `canResend()` already enforces a tighter
    // 1-per-30s / 5-per-hour budget per user; this is the
    // IP-level backstop for scripted abuse.
    Route::post('/email/verify/resend', [ResendVerificationController::class, 'store'])
        ->name('verification.resend')
        ->middleware('throttle:verify');

    // Everything below requires a verified email. The `verified`
    // middleware redirects to the verification notice when the user
    // has not yet confirmed their address. The three `verification.*`
    // routes and `logout` are exempt and live above.
    Route::middleware('verified')->group(function () {
        // 2FA challenge (Auth Phase 3). Lives BEFORE the
        // `two_factor` middleware wrapper so a user who still
        // has to pass the challenge can reach the form.
        // The challenge / verify / enable / disable / trusted-
        // devices routes are all explicitly named (see
        // EnsureTwoFactorVerified::BYPASS_ROUTE_NAMES) so they
        // would also bypass via the middleware if the user hit
        // them through a `two_factor`-wrapped group.
        Route::get('/two-factor/challenge', [TwoFactorChallengeController::class, 'create'])
            ->name('two-factor.challenge');
        // FASE Polish / v0.10.0 — per-IP 2FA challenge
        // throttle. The TOTP path is the typical case
        // (10/min); the recovery-code path is the weaker of
        // the two and gets a tighter cap (3/min). Both
        // throttles are stacked on the route — the IP-level
        // cap that fires first wins. The controller also
        // keeps its own per-user counter so a real user
        // hopping IPs can't outpace the per-user budget.
        Route::post('/two-factor/challenge', [TwoFactorChallengeController::class, 'store'])
            ->name('two-factor.verify')
            ->middleware('throttle:two-factor.challenge')
            ->middleware('throttle:two-factor.recovery');
    });

    // Routes that require a verified email AND a passed 2FA
    // challenge (or a trusted-device cookie). The middleware
    // exempts the two-factor.* / trusted-devices.* / logout route
    // names so a user mid-enrollment can still reach the
    // settings page and the challenge form.
    Route::middleware(['verified', 'two_factor'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Settings (Auth Phase 3 + future).
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/settings/security', [SettingsController::class, 'security'])
            ->name('settings.security');
        // FASE 4D — appearance (motion preferences)
        Route::get('/settings/appearance', [AppearanceController::class, 'show'])
            ->name('settings.appearance.show');
        Route::patch('/settings/appearance', [AppearanceController::class, 'update'])
            ->name('settings.appearance.update');

        // FASE 7 — language preference (i18n tri-língue)
        Route::get('/settings/idioma', [LocaleController::class, 'show'])
            ->name('settings.idioma.show');
        Route::patch('/settings/idioma', [LocaleController::class, 'update'])
            ->name('settings.idioma.update');

        // 2FA settings (Auth Phase 3).
        Route::post('/settings/security/two-factor/enable', [TwoFactorEnableController::class, 'beginEnable'])
            ->name('two-factor.enable.begin');
        Route::post('/settings/security/two-factor/disable', [TwoFactorDisableController::class, 'beginDisable'])
            ->name('two-factor.disable.begin');
        Route::delete('/settings/security/trusted-devices/{id}', [TrustedDeviceController::class, 'destroy'])
            ->name('trusted-devices.destroy');
        Route::delete('/settings/security/trusted-devices', [TrustedDeviceController::class, 'destroyAll'])
            ->name('trusted-devices.destroy-all');

        // Accounts
        Route::resource('accounts', AccountController::class);

        // Transactions
        Route::resource('transactions', TransactionController::class);
        Route::patch('/transactions/{transaction}/splits/{split}/toggle', [TransactionController::class, 'toggleSplit'])
            ->name('transactions.splits.toggle');

        // Recurrences (FASE 2A)
        Route::resource('recurrences', RecurrenceController::class);
        Route::post('/recurrences/{recurrence}/generate-now', [RecurrenceController::class, 'generateNow'])
            ->name('recurrences.generate-now');

        // Budgets (FASE 2B)
        Route::resource('budgets', BudgetController::class);
        Route::post('/budgets/{budget}/reset', [BudgetController::class, 'reset'])
            ->name('budgets.reset');

        // Goals (FASE 4A) — savings goals
        Route::resource('goals', GoalController::class);
        Route::post('/goals/{goal}/contribute', [GoalController::class, 'contribute'])
            ->name('goals.contribute');
        Route::post('/goals/{goal}/withdraw', [GoalController::class, 'withdraw'])
            ->name('goals.withdraw');

        // Subscriptions (FASE 4B) — tracked recurring services
        Route::resource('subscriptions', SubscriptionController::class);
        Route::post('/subscriptions/{subscription}/toggle-active', [SubscriptionController::class, 'toggleActive'])
            ->name('subscriptions.toggle-active');
        Route::post('/subscriptions/{subscription}/reactivate', [SubscriptionController::class, 'reactivate'])
            ->name('subscriptions.reactivate');

        // Debts (FASE 5) — tracked financing contracts with SAC/Price simulator
        Route::resource('debts', DebtController::class);
        Route::post('/debts/{debt}/simulate', [DebtController::class, 'simulate'])
            ->name('debts.simulate');
        Route::patch('/debts/{debt}/mark-paid', [DebtController::class, 'markAsPaidOff'])
            ->name('debts.mark-paid');

        // PIX (FASE 4C) — dedicated PIX UI
        Route::get('/pix', [PixController::class, 'index'])->name('pix.index');

        // Investments (FASE 5) — tracked portfolio positions
        Route::resource('investments', InvestmentController::class);

        // Reports (FASE 2C)
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

        // Tags (FASE 3A)
        Route::resource('tags', TagController::class);
        Route::post('/tags/{tag}/attach', [TagController::class, 'attach'])->name('tags.attach');
        Route::delete('/tags/{tag}/detach/{transaction}', [TagController::class, 'detach'])->name('tags.detach');

        // Profile
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        // FASE 5 — opt-in toggle for AI category suggestions.
        Route::patch('/profile/ai-preference', [ProfileController::class, 'updateAiPreference'])
            ->name('profile.ai-preference');
    });
});

/*
|--------------------------------------------------------------------------
| PWA static assets (FASE 5)
|--------------------------------------------------------------------------
|
| The manifest and the service worker live in /public but Laravel's
| test runner does not auto-serve static files (it routes every request
| through the kernel). We expose them through dedicated routes so:
|   1. The PWA works in environments where the public dir is not
|      served by the front controller (php artisan serve, shared
|      hosting, containers, etc).
|   2. Feature tests can hit them via route('pwa.manifest') and
|      route('pwa.service-worker').
*/
Route::get('/manifest.json', function () {
    $path = public_path('manifest.json');
    abort_unless(file_exists($path), 404);

    return response()->make(file_get_contents($path), 200, [
        'Content-Type' => 'application/manifest+json',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('pwa.manifest');

Route::get('/sw.js', function () {
    $path = public_path('sw.js');
    abort_unless(file_exists($path), 404);

    return response()->make(file_get_contents($path), 200, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Cache-Control' => 'no-cache, must-revalidate',
        'Service-Worker-Allowed' => '/',
    ]);
})->name('pwa.service-worker');

/*
| PWA assets: serve any file under public/pwa/ via the front controller.
| This mirrors what Apache/Nginx do with a static-files location block
| and keeps the test runner happy. Filenames are constrained to a
| tight charset to avoid path-traversal accidents.
*/
Route::get('/pwa/{file}', function (string $file) {
    abort_unless(preg_match('#^[A-Za-z0-9._\-]+$#', $file), 404);
    $path = public_path('pwa/'.$file);
    abort_unless(
        file_exists($path) && str_starts_with(realpath($path), realpath(public_path('pwa'))),
        404,
    );
    $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        default => 'application/octet-stream',
    };

    return response()->make(file_get_contents($path), 200, [
        'Content-Type' => $mime,
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('file', '[A-Za-z0-9._\-]+')->name('pwa.assets');

/*
|--------------------------------------------------------------------------
| Public pages (FASE 4D)
|--------------------------------------------------------------------------
|
| No auth required. These pages are accessible to guests and authenticated
| users alike.
*/
Route::get('/about', AboutController::class)->name('about');
Route::get('/tutorial', TutorialController::class)->name('tutorial');
Route::get('/tutorial/{chapter}', [TutorialController::class, 'chapter'])
    ->where('chapter', '[a-z-]+')
    ->name('tutorial.chapter');
