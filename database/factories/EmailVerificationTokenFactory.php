<?php

namespace Database\Factories;

use App\Models\EmailVerificationToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailVerificationToken>
 */
class EmailVerificationTokenFactory extends Factory
{
    protected $model = EmailVerificationToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'purpose' => EmailVerificationToken::PURPOSE_EMAIL_VERIFICATION,
            'token_hash' => EmailVerificationToken::hashToken(fake()->sha256()),
            'expires_at' => now()->addMinutes(EmailVerificationToken::TTL_MINUTES),
            'consumed_at' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn () => [
            'consumed_at' => now()->subSecond(),
        ]);
    }

    /**
     * Pin the token to a specific user instead of creating a new one.
     * Use this when the test already has a user in hand and just needs
     * a token row to belong to them.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Mint a token whose `purpose` column reads `password_reset`.
     * Combine with `forUser($u)` to fully scope the row.
     */
    public function passwordReset(): static
    {
        return $this->state(fn () => [
            'purpose' => EmailVerificationToken::PURPOSE_PASSWORD_RESET,
        ]);
    }
}
