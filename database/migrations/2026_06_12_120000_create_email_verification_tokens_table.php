<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email verification tokens (FASE 4D, Auth Phase 1).
 *
 * Stores a SHA-256 hash of a single-use random token issued when a user
 * registers or requests a re-send. The raw token is only ever shown in the
 * outgoing email and never persisted, so a database leak does not expose
 * usable verification links. Tokens expire 60 minutes after issue and are
 * marked `consumed_at` on first successful use; reuse is rejected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64);             // SHA-256 of the raw token
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
    }
};
