<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goals — financial savings goals the user is working toward.
 *
 * FASE 4A: tracks a target amount, optional deadline, and a running
 * `current_amount_cents` that the user updates manually (a "contribute"
 * action). When current >= target, `achieved_at` is stamped automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedBigInteger('target_amount_cents');
            $table->unsignedBigInteger('current_amount_cents')->default(0);
            $table->date('deadline')->nullable();
            $table->string('icon', 8)->default('🎯');
            $table->string('color', 9)->default('#f59e0b');
            $table->timestamp('achieved_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'archived_at']);
            $table->index(['user_id', 'achieved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
