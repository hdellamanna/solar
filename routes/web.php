<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Accounts
    Route::resource('accounts', AccountController::class);

    // Transactions
    Route::resource('transactions', TransactionController::class);
    Route::patch('/transactions/{transaction}/splits/{split}/toggle', [TransactionController::class, 'toggleSplit'])
        ->name('transactions.splits.toggle');

    // Recurrences (FASE 2A)
    Route::resource('recurrences', \App\Http\Controllers\RecurrenceController::class);
    Route::post('/recurrences/{recurrence}/generate-now', [\App\Http\Controllers\RecurrenceController::class, 'generateNow'])
        ->name('recurrences.generate-now');

    // Budgets (FASE 2B)
    Route::resource('budgets', \App\Http\Controllers\BudgetController::class);
    Route::post('/budgets/{budget}/reset', [\App\Http\Controllers\BudgetController::class, 'reset'])
        ->name('budgets.reset');

    // Goals (FASE 4A) — savings goals
    Route::resource('goals', \App\Http\Controllers\GoalController::class);
    Route::post('/goals/{goal}/contribute', [\App\Http\Controllers\GoalController::class, 'contribute'])
        ->name('goals.contribute');
    Route::post('/goals/{goal}/withdraw', [\App\Http\Controllers\GoalController::class, 'withdraw'])
        ->name('goals.withdraw');

    // Subscriptions (FASE 4B) — tracked recurring services
    Route::resource('subscriptions', \App\Http\Controllers\SubscriptionController::class);
    Route::post('/subscriptions/{subscription}/toggle-active', [\App\Http\Controllers\SubscriptionController::class, 'toggleActive'])
        ->name('subscriptions.toggle-active');
    Route::post('/subscriptions/{subscription}/reactivate', [\App\Http\Controllers\SubscriptionController::class, 'reactivate'])
        ->name('subscriptions.reactivate');

    // PIX (FASE 4C) — dedicated PIX UI
    Route::get('/pix', [\App\Http\Controllers\PixController::class, 'index'])->name('pix.index');

    // Investments (FASE 5) — tracked portfolio positions
    Route::resource('investments', \App\Http\Controllers\InvestmentController::class);

    // Reports (FASE 2C)
    Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');

    // Tags (FASE 3A)
    Route::resource('tags', TagController::class);
    Route::post('/tags/{tag}/attach', [TagController::class, 'attach'])->name('tags.attach');
    Route::delete('/tags/{tag}/detach/{transaction}', [TagController::class, 'detach'])->name('tags.detach');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // FASE 5 — opt-in toggle for the AI category suggester.
    Route::patch('/profile/ai-preference', [ProfileController::class, 'updateAiPreference'])
        ->name('profile.ai-preference');
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
    $path = public_path('pwa/' . $file);
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
