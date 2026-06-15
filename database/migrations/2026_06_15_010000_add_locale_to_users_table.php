<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FASE 7 — i18n tri-língue.
     *
     * Adds the per-user locale column. The application-layer constraint
     * (validated by the SetLocale middleware and the Settings/Locale
     * controller) limits values to one of `pt-BR`, `es`, `en`. The DB
     * column itself is unconstrained because SQLite (the dev/test
     * driver) does not support CHECK constraints with portable syntax.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 5)->default('pt-BR')->after('theme');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
