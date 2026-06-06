<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AccountController;
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

    // Recurrences (FASE 2A)
    Route::resource('recurrences', \App\Http\Controllers\RecurrenceController::class);
    Route::post('/recurrences/{recurrence}/generate-now', [\App\Http\Controllers\RecurrenceController::class, 'generateNow'])
        ->name('recurrences.generate-now');

    // Budgets (FASE 2B)
    Route::resource('budgets', \App\Http\Controllers\BudgetController::class);
    Route::post('/budgets/{budget}/reset', [\App\Http\Controllers\BudgetController::class, 'reset'])
        ->name('budgets.reset');

    // Placeholders for upcoming features
    Route::inertia('/reports', 'Placeholders/Reports')->name('reports.index');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});
