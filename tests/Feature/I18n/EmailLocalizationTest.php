<?php

namespace Tests\Feature\I18n;

use App\Mail\PasswordResetMail;
use App\Mail\TwoFactorDisableMail;
use App\Mail\TwoFactorEnableMail;
use App\Mail\VerifyEmailMail;
use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Verifies the 4 transactional
 * Mailables render in the recipient's `user.locale`.
 *
 * The `Mail::to($email)->locale($user->locale)` call the
 * service layer uses pins the Mailable's `$locale`
 * property; `Mailable::render()` wraps the view build in
 * a `withLocale()` closure so every `__()` lookup in the
 * Blade view (and `Mailable::envelope()` for the subject)
 * resolves against `lang/{locale}/mail.php`.
 *
 * The 4 cases below:
 *
 *   - Trigger each transactional email by hitting the
 *     route the user normally hits (resend, forgot
 *     password, enable 2FA, disable 2FA).
 *   - `Mail::fake()` first so the queued mail does not
 *     blow up.
 *   - `Mail::assertSent(Mailable::class, fn ($m) =>
 *     str_contains($m->render(), $expectedLocaleString))`
 *     — the closure renders the mailable under the
 *     Mailable's pinned locale and asserts a distinctive
 *     string from the right lang file appears.
 *
 * `Cache::flush()` in `setUp` clears the throttle counters
 * the rate-limiter middleware writes (see
 * `phpunit.xml`'s `CACHE_STORE=array`).
 */
class EmailLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_verify_email_renders_in_user_locale(): void
    {
        Mail::fake();

        $user = User::factory()->withLocale('es')->unverified()->create();

        $this->actingAs($user)->post(route('verification.resend'));

        Mail::assertSent(VerifyEmailMail::class, function (VerifyEmailMail $m) use ($user) {
            $rendered = $m->render();

            // The Spanish lang/pt-BR/mail.php's `verify.greeting`
            // is "Confirma tu correo electronico" — the
            // tilde is dropped for ASCII safety but the
            // Spanish prefix is distinctive. We use
            // `render()` which runs under the pinned
            // locale, so this is a real `__()` lookup.
            return $m->user->is($user)
                && str_contains($rendered, 'Confirma tu correo');
        });
    }

    public function test_reset_password_renders_in_user_locale(): void
    {
        Mail::fake();

        $user = User::factory()->withLocale('en')->create();

        $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $m) use ($user) {
            $rendered = $m->render();
            // English: "Reset your password" (greeting).
            return $m->user->is($user)
                && str_contains($rendered, 'Reset your password');
        });
    }

    public function test_2fa_enroll_renders_in_user_locale(): void
    {
        Mail::fake();

        $user = User::factory()->withLocale('es')->create();

        $this->actingAs($user)
            ->post(route('two-factor.enable.begin'));

        Mail::assertSent(TwoFactorEnableMail::class, function (TwoFactorEnableMail $m) use ($user) {
            $rendered = $m->render();
            // Spanish: "Activar verificacion en dos pasos".
            return $m->user->is($user)
                && str_contains($rendered, 'Activar verificacion en dos pasos');
        });
    }

    public function test_2fa_disable_renders_in_user_locale(): void
    {
        Mail::fake();

        // 2FA disable requires an existing 2FA row. The
        // `user_two_factor` table has `enabled_at` as a
        // required timestamp (NOT NULL) — the
        // confirmation step in the real flow stamps it.
        // We persist it directly here.
        $user = User::factory()->withLocale('en')->create();
        \App\Models\UserTwoFactor::create([
            'user_id' => $user->id,
            'secret_encrypted' => str_repeat('A', 64),
            'enabled_at' => now(),
            'confirmed_at' => now(),
        ]);

        // The BeginTwoFactorDisableRequest requires
        // `password` (current_password rule). Use the
        // factory default password "password".
        $this->actingAs($user)
            ->post(route('two-factor.disable.begin'), [
                'password' => 'password',
            ]);

        Mail::assertSent(TwoFactorDisableMail::class, function (TwoFactorDisableMail $m) use ($user) {
            $rendered = $m->render();
            // English: "Disable two-factor authentication".
            return $m->user->is($user)
                && str_contains($rendered, 'Disable two-factor authentication');
        });
    }
}
