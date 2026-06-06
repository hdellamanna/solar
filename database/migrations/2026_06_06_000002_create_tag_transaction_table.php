<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot table linking tags and transactions.
 * Composite primary key on (tag_id, transaction_id) with cascading deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_transaction', function (Blueprint $table) {
            $table->foreignId('tag_id')
                ->constrained('tags')
                ->cascadeOnDelete();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->primary(['tag_id', 'transaction_id']);
            $table->index('tag_id');
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_transaction');
    }
};
