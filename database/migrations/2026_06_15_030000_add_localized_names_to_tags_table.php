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
     * Adds localized name columns to the tags table. Mirrors the
     * categories migration:
     *
     *   - `name_pt` — Portuguese (Brazil), populated from the existing
     *     `name` column (data-preserving).
     *   - `name_es` — Spanish, nullable.
     *   - `name_en` — English, nullable.
     *
     * The original `name` column is kept for backward compatibility
     * with pre-FASE-7 code (the TagController's slug generator reads
     * `$tag->name` and seeders / tests insert via `'name' => ...`).
     * The model's `getNameAttribute()` accessor resolves the localized
     * name; the model's `creating` / `updating` events keep `name`
     * in sync with `name_pt` so the slug is derived from the stable
     * pt-BR value.
     */
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->string('name_pt')->nullable()->after('name');
            $table->string('name_es')->nullable()->after('name_pt');
            $table->string('name_en')->nullable()->after('name_es');
        });

        if (Schema::hasTable('tags') && Schema::hasColumn('tags', 'name')) {
            DB::table('tags')->whereNotNull('name')->update([
                'name_pt' => DB::raw('name'),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropColumn(['name_pt', 'name_es', 'name_en']);
        });
    }
};
