<?php

namespace Tests\Feature\I18n;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Frontend-track coverage.
 *
 * Tests that the localized category name surfaces correctly
 * through the Inertia payload and the `name` accessor in the
 * two locales the design calls out (es for active, pt-BR for
 * fallback).
 *
 * Owned by the i18n-frontend track — see
 * `/Users/hdellamanna/.mavis/plans/plan_394ef7f2/outputs/i18n-frontend/deliverable.md`.
 */
class LocalizedNameComponentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a category with the three localized name columns
     * populated, plus the legacy `name` column kept in sync
     * for pre-FASE-7 readers.
     */
    private function makeCategory(): Category
    {
        $cat = new Category();
        $cat->user_id = null;
        $cat->name = 'Alimentação';
        $cat->name_pt = 'Alimentação';
        $cat->name_es = 'Alimentación';
        $cat->name_en = 'Food';
        $cat->type = 'expense';
        $cat->icon = '🍔';
        $cat->color = '#f59e0b';
        $cat->is_default = true;
        $cat->save();
        return $cat;
    }

    /**
     * The accessor returns the active locale's name when set.
     * For locale=es and a category with all three names set,
     * the rendered `category.name` must be the Spanish variant.
     */
    public function test_renders_active_locale_name_when_all_three_are_set(): void
    {
        $category = $this->makeCategory();
        $user = User::factory()->create(['locale' => 'es']);

        $response = $this->actingAs($user)
            ->get(route('transactions.index'));

        $response->assertOk();
        // The Inertia payload must carry the localized name in
        // the categories list. The Spanish name is "Alimentación"
        // with the acute on the 'o'.
        $response->assertInertia(fn ($page) => $page
            ->component('Transactions/Index')
            ->has('categories', 1)
            ->where('categories.0.name', 'Alimentación')
            ->where('categories.0.name_pt', 'Alimentação')
            ->where('categories.0.name_es', 'Alimentación')
            ->where('categories.0.name_en', 'Food')
        );

        // Sanity-check the accessor too (used in many places
        // outside the Inertia payload — e.g. JSON serialization).
        app()->setLocale('es');
        $this->assertSame('Alimentación', $category->fresh()->name);

        // And the accessor's fallback chain still works: with
        // locale=pt-BR and only name_pt set (when the
        // Spanish variant is null), we fall back to the
        // pt-BR value (per the design doc fallback chain).
        $cat = new Category();
        $cat->user_id = null;
        $cat->name = 'Transporte';
        $cat->name_pt = 'Transporte';
        // Deliberately leave name_es and name_en null.
        $cat->type = 'expense';
        $cat->icon = '🚗';
        $cat->color = '#3b82f6';
        $cat->is_default = true;
        $cat->save();

        $user2 = User::factory()->create(['locale' => 'pt-BR']);
        $this->actingAs($user2)
            ->get(route('transactions.index'))
            ->assertInertia(fn ($page) => $page
                ->has('categories', 2)
                ->where('categories.1.name', 'Transporte')
                ->where('categories.1.name_pt', 'Transporte')
                ->where('categories.1.name_es', null)
                ->where('categories.1.name_en', null)
            );
    }
}
