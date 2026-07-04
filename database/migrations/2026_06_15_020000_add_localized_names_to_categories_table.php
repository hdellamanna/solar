<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FASE 7 — i18n tri-língue.
     *
     * Adds localized name columns to the categories table:
     *   - `name_pt` — Portuguese (Brazil), populated from the existing
     *     `name` column so the migration is data-preserving.
     *   - `name_es` — Spanish, nullable (a user may not have supplied it).
     *   - `name_en` — English, nullable.
     *
     * The original `name` column is KEPT (not dropped) so the pre-FASE-7
     * codebase — tests, seeders, the AI categorizer, etc. — keeps
     * working unmodified. New code reads through the Category model's
     * `getNameAttribute()` accessor which prefers the active locale's
     * column and falls back through pt-BR → es → en → `#id`.
     *
     * The accessor reads `name_pt` first, so this is the column that
     * should be set for new categories. `name` and `name_pt` are kept
     * in sync on insert/update by the model's `creating` / `updating`
     * events so existing query patterns (`where('name', $x)`) keep
     * matching.
     */
    public function up(): void
    {
        // Add the columns. SQLite doesn't support `after()` on existing
        // tables in older versions, so we let SQLite place them at the
        // end — the order is cosmetic, the names are what matters.
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('name_pt')->nullable()->after('name');
            $table->string('name_es')->nullable()->after('name_pt');
            $table->string('name_en')->nullable()->after('name_es');
        });

        // Backfill: copy the existing pt-BR `name` into `name_pt` so
        // the accessor can find a value to return for the default
        // locale. We do NOT touch `name` itself — the pre-FASE-7 code
        // path keeps reading `name` directly.
        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'name')) {
            DB::table('categories')->whereNotNull('name')->update([
                'name_pt' => DB::raw('name'),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['name_pt', 'name_es', 'name_en']);
        });
    }
};
