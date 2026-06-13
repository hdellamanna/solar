<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `purpose` column to the email_verification_tokens table so the
 * same shape can also carry password-reset tokens (FASE 4D / Auth
 * Phase 2). Old rows get the default value of `email_verification`,
 * which preserves the behaviour of every token already in the table.
 *
 * The new 3-column index `(user_id, purpose, expires_at)` replaces the
 * old 2-column one — purpose-aware lookups are now the only lookups
 * the application performs, so the narrower index is dead weight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_verification_tokens', function (Blueprint $table) {
            $table->string('purpose', 32)
                ->default('email_verification')
                ->after('user_id');

            $table->index(
                ['user_id', 'purpose', 'expires_at'],
                'evt_user_purpose_expires_idx',
            );

            // The new 3-column index covers the (user_id, expires_at)
            // prefix — drop the narrower one to keep the index list
            // small.
            $table->dropIndex(['user_id', 'expires_at']);
        });

        // CHECK constraint enforced at the database layer. SQLite
        // (used in dev + CI) honours the same syntax for CHECK
        // constraints, so this works across all targets.
        DB::statement(
            "ALTER TABLE email_verification_tokens "
            ."ADD CONSTRAINT evt_purpose_chk "
            ."CHECK (purpose IN ('email_verification', 'password_reset'))"
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE email_verification_tokens DROP CONSTRAINT evt_purpose_chk'
        );

        Schema::table('email_verification_tokens', function (Blueprint $table) {
            $table->dropIndex('evt_user_purpose_expires_idx');

            $table->index(['user_id', 'expires_at']);

            $table->dropColumn('purpose');
        });
    }
};
