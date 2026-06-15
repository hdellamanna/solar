<?php

namespace Database\Factories;

use App\Models\RecoveryCode;
use App\Models\User;
use App\Models\UserTwoFactor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // FASE 7 — i18n tri-língue. New users default to
            // pt-BR (the app's default locale). Override via the
            // `withLocale()` state method.
            'locale' => 'pt-BR',
        ];
    }

    /**
     * FASE 7 — set a specific locale on the factory-built user.
     *
     * Example:
     *   User::factory()->withLocale('es')->create();
     */
    public function withLocale(string $locale): static
    {
        if (! in_array($locale, ['pt-BR', 'es', 'en'], true)) {
            throw new \InvalidArgumentException("Unsupported locale [{$locale}]");
        }
        return $this->state(fn (array $attributes) => [
            'locale' => $locale,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Enable 2FA on the user by inserting a `user_two_factor` row with
     * a real, freshly-minted TOTP secret (encrypted at rest with the
     * app key, exactly like the production service does).
     *
     * Re-runnable: if a row already exists for the user, it is
     * replaced. Use this from the dedicated 2FA test suite to avoid
     * duplicating the encryption + secret-mint dance in every test.
     */
    public function withTwoFactor(): static
    {
        return $this->afterCreating(function (User $user): void {
            $google2fa = app(Google2FA::class);
            $plainSecret = $google2fa->generateSecretKey(32);

            UserTwoFactor::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'secret_encrypted' => Crypt::encryptString($plainSecret),
                    'last_counter' => 0,
                    'enabled_at' => now(),
                    'confirmed_at' => null,
                ],
            );
        });
    }

    /**
     * Insert `$count` SHA-256-hashed recovery codes for the user so
     * the live-challenge "use a recovery code" path can be exercised
     * without re-implementing the alphabet / format the service uses.
     *
     * The plain codes are returned via the closure so the test can
     * assert against one of them later (e.g. "redeem code ABCD-…").
     * Existing rows for the user are wiped first so the count is
     * deterministic.
     *
     * @param  int  $count
     * @return static
     */
    public function withRecoveryCodes(int $count = 10): static
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        return $this->afterCreating(function (User $user) use ($count, $alphabet): void {
            RecoveryCode::where('user_id', $user->id)->delete();

            for ($i = 0; $i < $count; $i++) {
                $bytes = random_bytes(10);
                $out = '';
                for ($j = 0; $j < 10; $j++) {
                    $out .= $alphabet[ord($bytes[$j]) % strlen($alphabet)];
                }
                $code = substr($out, 0, 4).'-'.substr($out, 4, 4).'-'.substr($out, 8, 2);

                RecoveryCode::create([
                    'user_id' => $user->id,
                    'code_hash' => hash('sha256', $code),
                ]);
            }
        });
    }

    /**
     * All motion flags off — simulates a user who has explicitly
     * chosen reduced motion and also has the OS preference enabled.
     */
    public function withReducedMotion(): static
    {
        return $this->state(fn (array $attributes) => [
            'motion_preference' => 'reduced',
            'motion_backdrop'   => false,
            'motion_spring'     => false,
            'motion_parallax'   => false,
        ]);
    }
}
