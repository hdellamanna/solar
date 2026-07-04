<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add 2FA + trusted-device support (FASE 4D / Auth Phase 3).
 *
 * Three operations, applied in this order:
 *
 * 1. Extend the `purpose` CHECK constraint on `email_verification_tokens`
 *    so the table can also carry `two_factor_enroll` and
 *    `two_factor_disable` tokens. Both are email-confirmed, one-time
 *    actions — same shape and security story as the existing
 *    `email_verification` and `password_reset` purposes. The same
 *    migration also adds a `meta` JSON column so the 2FA enable
 *    flow can stash the freshly-minted TOTP secret between the
 *    GET (renders the QR) and the POST (consumes the code)
 *    without a server-side session.
 *
 * 2. `user_two_factor` — one row per user, holds the APP-key-encrypted
 *    TOTP secret and the last counter the user successfully verified
 *    (replay protection). `enabled_at` is stamped the moment the
 *    enrollment flow confirms; `confirmed_at` is only set when the
 *    user has actually passed the live 2FA challenge at least once
 *    (kept for future audit, not currently required to gate anything).
 *
 * 3. `user_recovery_codes` — one row per recovery code, SHA-256 hashed
 *    (one-way, no recovery). `consumed_at` flips when the user redeems
 *    a code during the live challenge.
 *
 * 4. `trusted_devices` — one row per trusted device cookie issued.
 *    The cookie carries a 32-byte selector (random, unique, looked
 *    up on every request) and a 64-byte validator (SHA-256 hashed
 *    at rest). The pair works like a "remember me" token — the
 *    selector is a public lookup key, the validator is the secret
 *    proof of possession. `expires_at` is 90 days; `last_seen_at`
 *    is updated on every successful verify.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Extend the purpose CHECK constraint.
        //
        // NOTE: SQLite (3.46.1) does NOT support ALTER TABLE ADD CONSTRAINT CHECK.
        // MySQL/Postgres do. Driver-aware: SQLite uses BEFORE INSERT/UPDATE triggers
        // to emulate the constraint; MySQL/Postgres use ALTER TABLE ADD CONSTRAINT.
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement('ALTER TABLE email_verification_tokens DROP CONSTRAINT evt_purpose_chk');
            DB::statement(
                "ALTER TABLE email_verification_tokens "
                ."ADD CONSTRAINT evt_purpose_chk "
                ."CHECK (purpose IN ("
                ."'email_verification', 'password_reset', "
                ."'two_factor_enroll', 'two_factor_disable'"
                ."))"
            );
        } elseif ($driver === 'sqlite') {
            // Drop any pre-existing triggers from a previous run (defensive — fresh DB
            // doesn't have them, but a re-run after a partial migration might).
            DB::statement('DROP TRIGGER IF EXISTS evt_purpose_chk_insert');
            DB::statement('DROP TRIGGER IF EXISTS evt_purpose_chk_update');
            DB::statement(<<<'SQL'
                CREATE TRIGGER evt_purpose_chk_insert
                BEFORE INSERT ON email_verification_tokens
                FOR EACH ROW
                WHEN NEW.purpose NOT IN ('email_verification', 'password_reset', 'two_factor_enroll', 'two_factor_disable')
                BEGIN
                    SELECT RAISE(ABORT, 'evt_purpose_chk constraint failed: purpose must be one of: email_verification, password_reset, two_factor_enroll, two_factor_disable');
                END
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER evt_purpose_chk_update
                BEFORE UPDATE ON email_verification_tokens
                FOR EACH ROW
                WHEN NEW.purpose NOT IN ('email_verification', 'password_reset', 'two_factor_enroll', 'two_factor_disable')
                BEGIN
                    SELECT RAISE(ABORT, 'evt_purpose_chk constraint failed: purpose must be one of: email_verification, password_reset, two_factor_enroll, two_factor_disable');
                END
            SQL);
        }

        // The 2FA enable flow stashes the freshly-minted (encrypted)
        // TOTP secret on the token row between the GET and the POST
        // so the same secret powers both the QR the user scans and
        // the verify on submit. NULL for every other purpose.
        Schema::table('email_verification_tokens', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('user_agent');
        });

        // 2) user_two_factor — one row per user.
        Schema::create('user_two_factor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('secret_encrypted');                  // Crypt::encryptString($secret)
            $table->unsignedInteger('last_counter')->default(0);
            $table->timestamp('enabled_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        // 3) user_recovery_codes — one row per code (10 per user).
        Schema::create('user_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64);                   // SHA-256 hex
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'consumed_at']);
        });

        // 4) trusted_devices — one row per issued cookie.
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('selector', 32)->unique();          // public lookup key
            $table->string('validator_hash', 64);              // SHA-256(validator)
            $table->string('friendly_name')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
        Schema::dropIfExists('user_recovery_codes');
        Schema::dropIfExists('user_two_factor');

        Schema::table('email_verification_tokens', function (Blueprint $table) {
            $table->dropColumn('meta');
        });

        // Roll the CHECK constraint back to the PR2 shape.
        // Any token rows still using the new purposes will fail this
        // constraint after the down — caller's responsibility to scrub
        // those first (or run a down only in tests).
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement('ALTER TABLE email_verification_tokens DROP CONSTRAINT evt_purpose_chk');
            DB::statement(
                "ALTER TABLE email_verification_tokens "
                ."ADD CONSTRAINT evt_purpose_chk "
                ."CHECK (purpose IN ('email_verification', 'password_reset'))"
            );
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS evt_purpose_chk_insert');
            DB::statement('DROP TRIGGER IF EXISTS evt_purpose_chk_update');
            // Re-create the PR2-shape triggers (only email_verification + password_reset)
            DB::statement(<<<'SQL'
                CREATE TRIGGER evt_purpose_chk_insert
                BEFORE INSERT ON email_verification_tokens
                FOR EACH ROW
                WHEN NEW.purpose NOT IN ('email_verification', 'password_reset')
                BEGIN
                    SELECT RAISE(ABORT, 'evt_purpose_chk constraint failed: purpose must be one of: email_verification, password_reset');
                END
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER evt_purpose_chk_update
                BEFORE UPDATE ON email_verification_tokens
                FOR EACH ROW
                WHEN NEW.purpose NOT IN ('email_verification', 'password_reset')
                BEGIN
                    SELECT RAISE(ABORT, 'evt_purpose_chk constraint failed: purpose must be one of: email_verification, password_reset');
                END
            SQL);
        }
    }
};
