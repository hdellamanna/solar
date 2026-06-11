<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Investments — user-tracked portfolio positions (FASE 5).
 *
 * Each row is a single asset the user holds: a stock, a fund share, a
 * crypto coin, a fixed-income bond, or a treasury bond. Prices are
 * stored in **cents** to avoid floating-point drift, and quantity uses
 * 8 decimal places to support fractional crypto (BTC, ETH, etc).
 *
 * The `current_price_cents` is intentionally nullable: it's a manual
 * field for now, and FASE 9 will wire a live quote service. When null,
 * the P&L accessor simply returns 0 (no current valuation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 30); // stock, fund, crypto, fixed_income, treasury
            $table->string('ticker', 20)->nullable();
            $table->decimal('quantity', 18, 8)->default(0);
            $table->unsignedBigInteger('average_price_cents')->default(0);
            $table->unsignedBigInteger('current_price_cents')->nullable();
            $table->string('currency', 3)->default('BRL');
            $table->text('notes')->nullable();
            $table->date('acquired_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
