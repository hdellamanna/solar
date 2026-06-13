<?php

namespace Tests\Feature\Auth;

use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Coverage for FASE 4D / Auth Phase 3 — `purpose` column contract.
 *
 * The 4 cases below lock down the database CHECK constraint
 * that the 2FA migration added on `email_verification_tokens.purpose`.
 * The constraint accepts the four valid purposes and rejects
 * any other string; the password-reset lookup must remain
 * purpose-scoped so the 2FA rows do not leak into the reset
 * flow.
 */
class TwoFactorTokenPurposeTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 19. test_purpose_column_accepts_two_factor_enroll
    // ------------------------------------------------------------------
    public function test_purpose_column_accepts_two_factor_enroll(): void
    {
        $user = User::factory()->create();

        $token = EmailVerificationToken::create([
            'user_id' => $user->id,
            'purpose' => EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL,
            'token_hash' => hash('sha256', 'enroll-token-1'),
            'expires_at' => now()->addHour(),
        ]);

        $this->assertNotNull($token->id);
        $this->assertSame(
            EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL,
            $token->fresh()->purpose,
        );
    }

    // ------------------------------------------------------------------
    // 20. test_purpose_column_accepts_two_factor_disable
    // ------------------------------------------------------------------
    public function test_purpose_column_accepts_two_factor_disable(): void
    {
        $user = User::factory()->create();

        $token = EmailVerificationToken::create([
            'user_id' => $user->id,
            'purpose' => EmailVerificationToken::PURPOSE_TWO_FACTOR_DISABLE,
            'token_hash' => hash('sha256', 'disable-token-1'),
            'expires_at' => now()->addHour(),
        ]);

        $this->assertNotNull($token->id);
        $this->assertSame(
            EmailVerificationToken::PURPOSE_TWO_FACTOR_DISABLE,
            $token->fresh()->purpose,
        );
    }

    // ------------------------------------------------------------------
    // 21. test_purpose_column_rejects_unknown_value
    // ------------------------------------------------------------------
    public function test_purpose_column_rejects_unknown_value(): void
    {
        $user = User::factory()->create();

        // Direct INSERT bypassing Eloquent so the DB raises the
        // CHECK violation rather than the model layer (which
        // would silently accept anything).
        $this->expectException(QueryException::class);

        DB::table('email_verification_tokens')->insert([
            'user_id' => $user->id,
            'purpose' => 'totally-bogus-purpose',
            'token_hash' => hash('sha256', 'bogus-token-1'),
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------
    // 22. test_two_factor_tokens_do_not_leak_into_password_reset_query
    // ------------------------------------------------------------------
    public function test_two_factor_tokens_do_not_leak_into_password_reset_query(): void
    {
        $user = User::factory()->create();

        // Mint all four kinds of tokens for the same user.
        EmailVerificationToken::create([
            'user_id' => $user->id,
            'purpose' => EmailVerificationToken::PURPOSE_EMAIL_VERIFICATION,
            'token_hash' => hash('sha256', 'verify-token-1'),
            'expires_at' => now()->addHour(),
        ]);
        EmailVerificationToken::create([
            'user_id' => $user->id,
            'purpose' => EmailVerificationToken::PURPOSE_PASSWORD_RESET,
            'token_hash' => hash('sha256', 'reset-token-1'),
            'expires_at' => now()->addHour(),
        ]);
        EmailVerificationToken::create([
            'user_id' => $user->id,
            'purpose' => EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL,
            'token_hash' => hash('sha256', 'enroll-token-2'),
            'expires_at' => now()->addHour(),
        ]);
        EmailVerificationToken::create([
            'user_id' => $user->id,
            'purpose' => EmailVerificationToken::PURPOSE_TWO_FACTOR_DISABLE,
            'token_hash' => hash('sha256', 'disable-token-2'),
            'expires_at' => now()->addHour(),
        ]);

        // The password-reset lookup is purpose-scoped — the
        // scopeForPurpose() builder macro / the manual `where`
        // is what production code uses (see PR2's
        // PasswordResetService). A password-reset query must
        // return only the password_reset row, not the 2FA ones.
        $resetRows = EmailVerificationToken::query()
            ->forPurpose(EmailVerificationToken::PURPOSE_PASSWORD_RESET)
            ->where('user_id', $user->id)
            ->get();

        $this->assertCount(1, $resetRows);
        $this->assertSame(
            EmailVerificationToken::PURPOSE_PASSWORD_RESET,
            $resetRows->first()->purpose,
        );

        // And, symmetrically, the 2FA-purpose queries return
        // only their own row.
        $enrollRows = EmailVerificationToken::query()
            ->forPurpose(EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL)
            ->where('user_id', $user->id)
            ->get();
        $this->assertCount(1, $enrollRows);
        $this->assertSame(
            EmailVerificationToken::PURPOSE_TWO_FACTOR_ENROLL,
            $enrollRows->first()->purpose,
        );

        $disableRows = EmailVerificationToken::query()
            ->forPurpose(EmailVerificationToken::PURPOSE_TWO_FACTOR_DISABLE)
            ->where('user_id', $user->id)
            ->get();
        $this->assertCount(1, $disableRows);
        $this->assertSame(
            EmailVerificationToken::PURPOSE_TWO_FACTOR_DISABLE,
            $disableRows->first()->purpose,
        );
    }
}
