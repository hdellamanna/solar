<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_suggestion_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('description_hash', 64);
            $table->foreignId('suggested_category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('provider', 32)->default('rules');
            $table->decimal('confidence', 5, 4)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->unique(['user_id', 'description_hash'], 'ai_suggestion_cache_user_hash_unique');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_suggestion_cache');
    }
};
