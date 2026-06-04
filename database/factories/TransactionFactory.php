<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['income', 'expense']);
        $amount = $type === 'income'
            ? fake()->numberBetween(20000, 1500000) // R$ 200 to R$ 15.000
            : -fake()->numberBetween(500, 200000); // -R$ 5 to -R$ 2.000

        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'destination_account_id' => null,
            'category_id' => null,
            'type' => $type,
            'amount_cents' => $amount,
            'date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'description' => fake()->sentence(3),
            'notes' => null,
            'status' => 'paid',
            'is_pix' => false,
            'pix_key' => null,
            'recurrence_id' => null,
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => [
            'type' => 'income',
            'amount_cents' => fake()->numberBetween(20000, 1500000),
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn () => [
            'type' => 'expense',
            'amount_cents' => -fake()->numberBetween(500, 200000),
        ]);
    }

    public function transfer(Account $from, Account $to): static
    {
        $amount = fake()->numberBetween(1000, 200000);
        return $this->state(fn () => [
            'type' => 'transfer',
            'account_id' => $from->id,
            'destination_account_id' => $to->id,
            'category_id' => null,
            'amount_cents' => -$amount,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }
}
