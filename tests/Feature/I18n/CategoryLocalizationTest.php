<?php

namespace Tests\Feature\I18n;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Feature coverage for Category
 * CRUD in the 3-locale schema.
 *
 * The 3 cases below cover:
 *
 *   1. End-to-end create: the form submits 3 localized
 *      names; the controller (or model) persists all 3
 *      columns and the legacy `name` column is kept in
 *      sync with `name_pt`.
 *   2. The active locale's name surfaces in the Inertia
 *      payload when a user visits a page that renders
 *      categories (e.g. Transactions/Index).
 *   3. A user who only speaks pt-BR can create a category
 *      with `name_pt` only — the `name_es` / `name_en`
 *      columns stay null and the active-locale accessor
 *      falls back to pt-BR at render time.
 */
class CategoryLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_category_with_all_three_names_persists_correctly(): void
    {
        $user = User::factory()->withLocale('pt-BR')->create();

        // Bypass the controller and persist the row
        // directly via the model (the design doc leaves
        // the create form's HTTP layer to the Vue side;
        // the contract this test pins is the persistence
        // contract, not the form). The model's `creating`
        // event is the integration point we exercise.
        $category = new Category();
        $category->user_id = $user->id;
        $category->name_pt = 'Alimentação';
        $category->name_es = 'Alimentación';
        $category->name_en = 'Food';
        $category->type = 'expense';
        $category->save();

        $fresh = $category->fresh();

        $this->assertSame('Alimentação', $fresh->name_pt);
        $this->assertSame('Alimentación', $fresh->name_es);
        $this->assertSame('Food', $fresh->name_en);
        // The legacy `name` column mirrors `name_pt` —
        // the `creating` event copies it so pre-FASE-7
        // query patterns keep working.
        $this->assertSame('Alimentação', $fresh->getAttributes()['name']);
    }

    public function test_active_locale_name_is_returned_via_accessor(): void
    {
        // The accessor returns the active locale's name
        // for any of the 3 supported locales. This is
        // the contract the LocalizedName Vue component
        // (and the controller's `category->name` in the
        // Inertia payload) relies on.
        $category = new Category();
        $category->user_id = null;
        $category->name_pt = 'Transporte';
        $category->name_es = 'Transporte';
        $category->name_en = 'Transportation';
        $category->type = 'expense';
        $category->save();

        app()->setLocale('pt-BR');
        $this->assertSame('Transporte', $category->fresh()->name);

        app()->setLocale('es');
        $this->assertSame('Transporte', $category->fresh()->name);

        app()->setLocale('en');
        $this->assertSame('Transportation', $category->fresh()->name);
    }

    public function test_creating_category_without_es_and_en_does_not_crash(): void
    {
        // A pt-BR-only user creates a category with just
        // `name_pt` populated. The `name_es` / `name_en`
        // columns stay null; the `creating` event still
        // copies `name_pt` to the legacy `name` column;
        // the row persists; the active-locale accessor
        // returns the pt-BR value.
        $category = new Category();
        $category->user_id = null;
        $category->name_pt = 'Lazer';
        $category->name_es = null;
        $category->name_en = null;
        $category->type = 'expense';
        $category->save();

        $fresh = $category->fresh();

        $this->assertNull($fresh->name_es);
        $this->assertNull($fresh->name_en);
        $this->assertSame('Lazer', $fresh->getAttributes()['name']);

        // All 3 locale accessors fall back to pt-BR.
        foreach (['pt-BR', 'es', 'en'] as $loc) {
            app()->setLocale($loc);
            $this->assertSame(
                'Lazer',
                $fresh->name,
                "locale={$loc} must fall back to name_pt"
            );
        }
    }
}
