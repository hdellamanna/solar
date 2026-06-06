<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recurrence>
 */
class RecurrenceFactory extends Factory
{
    protected $model = Recurrence::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['income', 'expense']);
        $amount = $type === 'income'
            ? fake()->numberBetween(50000, 1500000)
            : fake()->numberBetween(1000, 200000);

        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'category_id' => null,
            'description' => fake()->sentence(3),
            'amount_cents' => $amount,
            'type' => $type,
            'frequency' => 'monthly',
            'starts_at' => CarbonImmutable::today()->subDays(random_int(0, 90)),
            'ends_at' => null,
            'last_generated_at' => null,
            'active' => true,
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => [
            'type' => 'income',
            'amount_cents' => fake()->numberBetween(50000, 1500000),
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn () => [
            'type' => 'expense',
            'amount_cents' => fake()->numberBetween(1000, 200000),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    /**
     * Convenience: build a realistic recurrence for a (user, account, category) trio.
     */
    public function configureFor(User $user, Account $account, ?Category $category = null, int $amountCents = 10000, string $type = 'expense', string $frequency = 'monthly', ?string $startsAt = null): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category?->id,
            'amount_cents' => $amountCents,
            'type' => $type,
            'frequency' => $frequency,
            'starts_at' => $startsAt ?: CarbonImmutable::today()->subDays(random_int(0, 90))->toDateString(),
        ]);
    }
}
