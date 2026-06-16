<?php

namespace Tests\Feature\I18n;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Frontend-track coverage.
 *
 * Verifies the Transactions index renders the active locale's
 * category name across all 3 supported locales. The
 * `LocalizableName` Vue component is bound to the model's
 * `name` accessor, which the controller evaluates against
 * `app()->getLocale()` — that locale is driven by the
 * `SetLocale` middleware (user > header > cookie > config).
 *
 * Switching the user's `locale` and reloading must surface
 * the new name in the Inertia payload.
 *
 * Owned by the i18n-frontend track.
 */
class CategoryDisplayInLocaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create one category with all three localized names
     * populated. We use the same fixture across both cases so
     * the test proves the same row renders in 2 different
     * languages, not that 2 different fixtures happen to
     * match.
     */
    private function makeCategory(): Category
    {
        $cat = new Category();
        $cat->user_id = null;
        $cat->name = 'Alimentação'; // legacy / pt-BR
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

    public function test_user_with_locale_es_sees_spanish_then_english_after_switch(): void
    {
        $this->makeCategory();
        $user = User::factory()->create(['locale' => 'es']);

        // First request: Spanish
        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Transactions/Index')
                ->has('categories', 1)
                ->where('categories.0.name', 'Alimentación')
            );

        // Switch the user's stored locale to English and
        // re-request. The SetLocale middleware should pick
        // the new value up on the next request and the
        // Inertia payload should reflect the English name.
        $user->locale = 'en';
        $user->save();

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Transactions/Index')
                ->has('categories', 1)
                ->where('categories.0.name', 'Food')
            );
    }
}
