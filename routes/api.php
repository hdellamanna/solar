<?php

use App\Http\Controllers\Api\AiCategorizeController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (used by Inertia front-end for autocomplete / AJAX lookups)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/tags', [TagController::class, 'apiIndex'])->name('api.tags.index');
    Route::get('/search', SearchController::class)->name('api.search');

    // FASE 5 — AI-powered category suggestion.
    Route::post('/ai/suggest-category', [AiCategorizeController::class, 'suggestCategory'])
        ->name('api.ai.suggest-category');
});
