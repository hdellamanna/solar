<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved PIX keys (FASE 4C).
 *
 * The user can save the PIX keys they regularly use so they don't
 * have to retype them when generating a BR Code or registering a
 * transaction. One row per key, scoped to the user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pix_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60);            // "Pai", "Mãe", "Aluguel"
            $table->string('key', 120);             // the actual key
            $table->string('type', 12);             // cpf | cnpj | email | phone | evp
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pix_keys');
    }
};
