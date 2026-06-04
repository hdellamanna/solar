<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Nubank', 'Inter', 'Itaú', 'Carteira', 'XP Inc']) . ' ' . fake()->word(),
            'type' => fake()->randomElement(array_keys(Account::TYPES)),
            'currency' => 'BRL',
            'color' => fake()->hexColor(),
            'icon' => 'wallet',
            'initial_balance_cents' => fake()->numberBetween(0, 1000000),
            'archived' => false,
        ];
    }

    public function checking(): static
    {
        return $this->state(fn () => ['type' => 'checking']);
    }

    public function savings(): static
    {
        return $this->state(fn () => ['type' => 'savings']);
    }

    public function creditCard(): static
    {
        return $this->state(fn () => ['type' => 'credit_card']);
    }
}
