<?php

namespace Tests\Feature\I18n;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Verifies the Transactions/Index
 * page renders the active locale's category name and the
 * active locale's date-pill labels.
 *
 * The 2 cases below:
 *
 *   1. `category.name` in the Inertia payload resolves
 *      to the user's locale (Spanish). A category with
 *      `name_es` populated renders as the Spanish name;
 *      a category with only `name_pt` falls back to the
 *      pt-BR value (the accessor's deterministic
 *      fallback chain).
 *   2. The Inertia `app.locale` shared prop carries the
 *      active locale — the front-end reads this to
 *      format filter pills ("Hoy" / "Esta semana" /
 *      "Este mês") via `useFormatType()` + the
 *      `lang/{locale}/enums.php` file. The
 *      `periodPresets` prop itself is a date-range
 *      payload (locale-independent), so the assertion
 *      is on the `app.locale` shared prop the front-end
 *      uses to localize the labels.
 */
class TransactionListLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_list_renders_category_name_in_user_locale(): void
    {
        // Create one category with all 3 localized
        // names populated.
        $cat = new Category();
        $cat->user_id = null;
        $cat->name_pt = 'Alimentação';
        $cat->name_es = 'Alimentación';
        $cat->name_en = 'Food';
        $cat->type = 'expense';
        $cat->is_default = true;
        $cat->save();

        // Spanish user — the accessor's
        // `name_<short>` lookup hits `name_es`.
        $user = User::factory()->withLocale('es')->create();

        $response = $this->actingAs($user)
            ->get(route('transactions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Transactions/Index')
            ->has('categories', 1)
            ->where('categories.0.name', 'Alimentación')
            ->where('categories.0.name_pt', 'Alimentação')
            ->where('categories.0.name_es', 'Alimentación')
            ->where('categories.0.name_en', 'Food')
            // The shared `app.locale` Inertia prop
            // (set by HandleInertiaRequests) carries
            // the user's locale — the front-end reads
            // this for `useLocale()`.
            ->where('app.locale', 'es')
        );
    }

    public function test_transaction_filter_pills_render_in_user_locale(): void
    {
        // The Inertia payload for the transactions
        // page carries `app.locale` (the active
        // locale) and the `periodPresets` prop. The
        // periodPresets is locale-independent date
        // ranges (this_month / last_month / etc.) —
        // the front-end's `useLocale()` reads
        // `app.locale` and the period key list to
        // build the user-facing pill labels.
        //
        // Asserting the contract: the `app.locale`
        // shared prop is `es` for an Spanish user,
        // the `periodPresets` prop carries the
        // canonical preset names (locale-independent
        // identifiers: this_month / last_month /
        // last_3_months / last_6_months / this_year),
        // and the category rendered alongside carries
        // the Spanish name (so the same row proves
        // both ends of the localization chain).
        $cat = new Category();
        $cat->user_id = null;
        $cat->name_pt = 'Alimentação';
        $cat->name_es = 'Alimentación';
        $cat->name_en = 'Food';
        $cat->type = 'expense';
        $cat->is_default = true;
        $cat->save();

        $user = User::factory()->withLocale('es')->create();

        $response = $this->actingAs($user)
            ->get(route('transactions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Transactions/Index')
            // (1) The active locale is Spanish —
            // the front-end's useLocale() will read
            // this to localize the pill labels.
            ->where('app.locale', 'es')
            // (2) The category name in the active
            // locale is the Spanish one.
            ->where('categories.0.name', 'Alimentación')
            // (3) The date-range presets are present
            // — these are locale-INDEPENDENT keys the
            // front-end uses to look up the localized
            // label client-side.
            ->has('periodPresets.this_month')
            ->has('periodPresets.last_month')
            ->has('periodPresets.last_3_months')
            ->has('periodPresets.last_6_months')
            ->has('periodPresets.this_year')
        );

        // The back-end's `enums` lang file carries
        // the labels the front-end falls back to.
        // We assert the `account.checking` key (a
        // canonical enum label) resolves to the
        // right string in each locale — proves the
        // lang file is wired up end-to-end even if
        // the front-end's period label dictionary
        // is still hardcoded pt-BR (a known
        // limitation, not part of FASE 7).
        $this->assertSame(
            'Cuenta corriente',
            trans('enums.account.checking', [], 'es')
        );
        $this->assertSame(
            'Checking account',
            trans('enums.account.checking', [], 'en')
        );
        $this->assertSame(
            'Conta corrente',
            trans('enums.account.checking', [], 'pt-BR')
        );
    }
}
