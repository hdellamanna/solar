<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * account_balances — sub-balance per currency on a single account (FASE 6A).
 *
 * A multi-currency account (Wise, Nomad Global, C6 Global, Inter Global)
 * holds balances in several currencies at once. The base `accounts`
 * row still carries the account's "home" currency, and the
 * `accounts.initial_balance_cents` is interpreted in that home
 * currency. Every other currency held in the account lives here,
 * one row per (account, currency) pair.
 *
 * For ordinary single-currency accounts, the user can ignore this
 * table — the existing balance logic (initial + signed transactions
 * in the account's currency) keeps working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->bigInteger('balance_cents')->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'currency']);
            $table->index('currency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_balances');
    }
};
