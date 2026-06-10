<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `currency` and `exchange_rate_cents` to transactions (FASE 6A).
 *
 * `currency` defaults to the account's home currency; users can
 * override it at transaction time (e.g. a USD card charged in EUR).
 * `exchange_rate_cents` is the rate captured at the moment the
 * transaction was created: 1 unit of `currency` = `exchange_rate_cents/100`
 * of the user's `home_currency`. It is purely a snapshot — the
 * live FX rate can change later without retroactively affecting
 * historical reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('amount_cents');
            $table->unsignedInteger('exchange_rate_cents')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate_cents']);
        });
    }
};
