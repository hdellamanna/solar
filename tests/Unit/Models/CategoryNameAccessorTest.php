<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Unit coverage for the
 * `Category::getNameAttribute()` magic accessor and the
 * `creating` event that back-fills the legacy `name` column.
 *
 * The accessor's resolution order, per the model docblock, is:
 *
 *   1. The active locale's column (`name_<short>` of
 *      `app()->getLocale()`; e.g. `name_pt` for pt-BR)
 *   2. `name_pt` (pt-BR is the canonical source)
 *   3. `name_es`
 *   4. `name_en`
 *   5. The legacy `name` column (kept in sync by the
 *      `creating` event for pre-FASE-7 query compatibility)
 *   6. The row's id (`#<id>`)
 *
 * The 4 cases below lock in the fallback chain and the
 * `creating` event's contract.
 */
class CategoryNameAccessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_name_in_active_locale(): void
    {
        $category = new Category();
        $category->name_pt = 'Alimentação';
        $category->name_es = 'Alimentación';
        $category->name_en = 'Food';
        $category->type = 'expense';
        $category->save();

        app()->setLocale('pt-BR');
        $this->assertSame('Alimentação', $category->fresh()->name);

        app()->setLocale('es');
        $this->assertSame('Alimentación', $category->fresh()->name);

        app()->setLocale('en');
        $this->assertSame('Food', $category->fresh()->name);
    }

    public function test_falls_back_to_pt_br_when_active_locale_missing(): void
    {
        // User wrote the category in pt-BR only. With
        // locale=es the accessor must fall back to
        // `name_pt` rather than crash or render an empty
        // string.
        $category = new Category();
        $category->name_pt = 'Transporte';
        $category->name_es = null;
        $category->name_en = null;
        $category->type = 'expense';
        $category->save();

        app()->setLocale('es');
        $this->assertSame('Transporte', $category->fresh()->name);

        app()->setLocale('en');
        $this->assertSame('Transporte', $category->fresh()->name);
    }

    public function test_falls_back_to_id_when_all_names_null(): void
    {
        // An empty row (e.g. created by a buggy seed or a
        // wiped migration) must not return an empty
        // string. The accessor falls back to `#<id>` so
        // the UI always has a non-empty string to render.
        //
        // The DB schema marks `name` NOT NULL — we
        // persist an empty string to satisfy the
        // constraint, and verify the accessor still
        // resolves to `#<id>` (the empty string is
        // treated as "no value" by the accessor's
        // `$candidate !== ''` guard).
        $category = new Category();
        $category->name_pt = null;
        $category->name_es = null;
        $category->name_en = null;
        // Set the legacy `name` column to '' so the NOT NULL
        // constraint does not reject the insert. The
        // accessor's `??` chain and `!== ''` guard then
        // route the lookup to the `#<id>` fallback.
        $category->name = '';
        $category->type = 'expense';
        $category->save();

        app()->setLocale('es');
        $name = $category->fresh()->name;

        $this->assertNotEmpty($name);
        $this->assertStringStartsWith('#', $name);
        $this->assertStringContainsString((string) $category->id, $name);
    }

    public function test_creating_event_backfills_missing_locales(): void
    {
        // The model's `creating` event keeps the legacy
        // `name` column in sync with `name_pt` so the
        // pre-FASE-7 query patterns (`where('name', $x)`,
        // `firstOrCreate(['name' => $x, ...])`) keep
        // matching. Verify the sync by reading the raw
        // underlying attribute (the public `$c->name`
        // accessor would re-trigger the locale lookup
        // and mask the test).
        $category = new Category();
        $category->name_pt = 'Lazer';
        $category->name_es = null;
        $category->name_en = null;
        $category->type = 'expense';
        $category->save();

        $rawName = $category->getAttributes()['name'] ?? null;
        $this->assertSame(
            'Lazer',
            $rawName,
            'creating event must copy name_pt into the legacy `name` column'
        );
    }
}
