<?php

namespace Tests\Feature\Auth;

use App\Mail\TwoFactorDisableMail;
use App\Models\EmailVerificationToken;
use App\Models\RecoveryCode;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Coverage for FASE 4D / Auth Phase 3 — 2FA disable flow.
 *
 * The 3 cases below exercise the password-gated begin + the
 * email-link confirm + the full state-wipe. The "wrong password"
 * case locks down the defense-in-depth re-prompt before the
 * email link is sent.
 */
class TwoFactorDisableTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 16. test_user_can_begin_2fa_disable
    // ------------------------------------------------------------------
    public function test_user_can_begin_2fa_disable(): void
    {
        Mail::fake();

        $user = User::factory()
            ->withTwoFactor()
            ->create([
                'password' => 'correct-horse-9876',
            ]);

        $response = $this->actingAs($user)
            ->from('/settings/security')
            ->post(route('two-factor.disable.begin'), [
                'password' => 'correct-horse-9876',
            ]);

        $response->assertRedirect('/settings/security');
        $response->assertSessionHas('success', fn ($msg) => str_contains(
            (string) $msg,
            'Enviamos um link',
        ));

        // Token row with the right purpose.
        $this->assertDatabaseHas('email_verification_tokens', [
            'user_id' => $user->id,
            'purpose' => EmailVerificationToken::PURPOSE_TWO_FACTOR_DISABLE,
            'consumed_at' => null,
        ]);

        Mail::assertSent(TwoFactorDisableMail::class, function (TwoFactorDisableMail $mail) use ($user) {
            return $mail->user->is($user);
        });
    }

    // ------------------------------------------------------------------
    // 17. test_disable_begin_rejects_wrong_password
    // ------------------------------------------------------------------
    public function test_disable_begin_rejects_wrong_password(): void
    {
        Mail::fake();

        $user = User::factory()
            ->withTwoFactor()
            ->create([
                'password' => 'right-password-9876',
            ]);

        $response = $this->actingAs($user)
            ->from('/settings/security')
            ->post(route('two-factor.disable.begin'), [
                'password' => 'WRONG-password-9876',
            ]);

        // The FormRequest's `current_password` rule fires and
        // surfaces a field-level validation error.
        $response->assertRedirect('/settings/security');
        $response->assertSessionHasErrors(['password']);

        // No disable token row, no mail.
        $this->assertDatabaseMissing('email_verification_tokens', [
            'user_id' => $user->id,
            'purpose' => EmailVerificationToken::PURPOSE_TWO_FACTOR_DISABLE,
        ]);
        Mail::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // 18. test_user_can_confirm_2fa_disable
    // ------------------------------------------------------------------
    public function test_user_can_confirm_2fa_disable(): void
    {
        Mail::fake();

        $user = User::factory()
            ->withTwoFactor()
            ->withRecoveryCodes(10)
            ->create([
                'password' => 'disable-pass-1234',
            ]);

        // Pre-seed a trusted device so we can assert it gets
        // wiped as part of the disable cascade.
        TrustedDevice::create([
            'user_id' => $user->id,
            'selector' => 'sel_to_be_wiped',
            'validator_hash' => hash('sha256', 'val_to_be_wiped'),
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(90),
        ]);
        $this->assertSame(1, TrustedDevice::where('user_id', $user->id)->count());

        // Begin disable.
        $this->actingAs($user)
            ->from('/settings/security')
            ->post(route('two-factor.disable.begin'), [
                'password' => 'disable-pass-1234',
            ])
            ->assertSessionHas('success');

        $disableUrl = null;
        Mail::assertSent(TwoFactorDisableMail::class, function ($mail) use (&$disableUrl) {
            $disableUrl = $mail->confirmUrl;

            return true;
        });
        $rawToken = $this->extractRawTokenFromUrl($disableUrl);

        // The user has now been redirected / logged out. The
        // email-link click arrives in a fresh browser, so the
        // user is NOT authenticated at the disable store.
        auth()->logout();

        $response = $this->post(route('two-factor.disable.store'), [
            'token' => $rawToken,
            'password' => 'disable-pass-1234',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success', fn ($msg) => str_contains(
            (string) $msg,
            '2FA desativado',
        ));

        // Every 2FA-related row is gone.
        $this->assertDatabaseMissing('user_two_factor', [
            'user_id' => $user->id,
        ]);
        $this->assertSame(0, RecoveryCode::where('user_id', $user->id)->count());
        $this->assertSame(0, TrustedDevice::where('user_id', $user->id)->count());
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    private function extractRawTokenFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $segments = array_values(array_filter(explode('/', $path)));

        return (string) end($segments);
    }
}
