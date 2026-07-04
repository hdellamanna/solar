<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `home_currency` to users (FASE 6A).
 *
 * The user's reporting currency. All dashboard widgets that show
 * "total balance" convert other currencies to this one via the
 * cached FX rate from {@see App\Services\FxRateService}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('home_currency', 3)->default('BRL')->after('use_ai_categorize');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('home_currency');
        });
    }
};
