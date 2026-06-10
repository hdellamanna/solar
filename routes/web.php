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

    // Reports (FASE 2C)
    Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');

    // Tags (FASE 3A)
    Route::resource('tags', TagController::class);
    Route::post('/tags/{tag}/attach', [TagController::class, 'attach'])->name('tags.attach');
    Route::delete('/tags/{tag}/detach/{transaction}', [TagController::class, 'detach'])->name('tags.detach');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});
