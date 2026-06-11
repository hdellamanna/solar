<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Debts — financing contracts the user is paying off (FASE 5).
 *
 * A debt tracks the original creditor, the current outstanding
 * balance, the agreed annual interest rate, the monthly payment
 * amount, when payments started and which amortization system is
 * being used (SAC — fixed principal, or Price — fixed payment).
 * The optional `payoff_strategy` column drives the SAC vs Price
 * simulator that ships alongside this model — see
 * {@see \App\Services\AmortizationService}.
 *
 * Money is stored in `*_cents` (integer). The rate is a decimal
 * (0.1250 = 12.50% a.a.). `is_paid_off` + `paid_off_at` are
 * stamped when the user marks the debt as fully settled via the
 * `markAsPaidOff` controller action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('creditor', 80);                          // "Banco do Brasil", "Itaú", "Cartão Nubank"
            $table->string('description', 120)->nullable();         // "Financiamento do carro", "Cartão de crédito"
            $table->unsignedBigInteger('total_balance_cents');      // outstanding balance
            $table->decimal('interest_rate_annual', 8, 4)->default(0); // 0.1250 = 12.50% a.a.
            $table->unsignedBigInteger('monthly_payment_cents');    // user's monthly payment
            $table->date('start_date');
            $table->string('payoff_strategy', 8)->default('sac');   // 'sac' | 'price'
            $table->string('currency', 3)->default('BRL');
            $table->text('notes')->nullable();
            $table->boolean('is_paid_off')->default(false);
            $table->timestamp('paid_off_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_paid_off']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
