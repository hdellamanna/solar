<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions — recurring service charges the user is paying for
 * (Netflix, Spotify, iCloud, Notion, gym, etc).
 *
 * FASE 4B: tracks the next billing date derived from `billing_day`,
 * with a soft pause (active=false) and a soft cancel (cancelled_at).
 * The optional `recurrence_id` links the subscription to an existing
 * Recurrence so the user can see both the rule and the subscription
 * metadata in one place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->unsignedTinyInteger('billing_day'); // 1-31
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recurrence_id')->nullable()->constrained()->nullOnDelete();
            $table->string('icon', 8)->default('📺');
            $table->string('color', 9)->default('#ef4444');
            $table->boolean('active')->default(true);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'active']);
            $table->index(['user_id', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
