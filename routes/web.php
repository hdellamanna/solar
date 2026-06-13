<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResendVerificationController;
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
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

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
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    // Password reset (FASE 4D / Auth Phase 2). Anyone — even an
    // unauthenticated visitor — can hit these.
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    // The GET form route deliberately does NOT use the `signed`
    // middleware: the controller does the validity check itself and
    // bounces the user back to forgot-password with a friendly
    // error flash (the design's "bad token" UX). The signature on
    // the URL still gives us a hard 60-minute TTL at the database
    // level via the token's `expires_at`.
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->name('password.update');
});

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
    Route::post('/email/verify/resend', [ResendVerificationController::class, 'store'])
        ->name('verification.resend');

    // Everything below requires a verified email. The `verified`
    // middleware redirects to the verification notice when the user
    // has not yet confirmed their address. The three `verification.*`
    // routes and `logout` are exempt and live above.
    Route::middleware('verified')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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
