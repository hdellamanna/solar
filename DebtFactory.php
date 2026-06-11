<?php

namespace Database\Factories;

use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debt>
 */
class DebtFactory extends Factory
{
    protected $model = Debt::class;

    public function definition(): array
    {
        $creditor = fake()->randomElement([
            'Banco do Brasil', 'Itaú', 'Santander', 'Bradesco',
            'Caixa', 'Nubank', 'Inter', 'C6 Bank',
        ]);

        return [
            'user_id' => User::factory(),
            'creditor' => $creditor,
            'description' => fake()->randomElement([
                'Financiamento do carro',
                'Cartão de crédito',
                'Empréstimo pessoal',
                'Financiamento imobiliário',
                'CDC',
            ]),
            'total_balance_cents' => fake()->numberBetween(50000, 5_000_000),
            'interest_rate_annual' => fake()->randomFloat(4, 0.01, 0.30),
            'monthly_payment_cents' => fake()->numberBetween(10000, 200_000),
            'start_date' => now()->subMonths(fake()->numberBetween(1, 36))->toDateString(),
            'payoff_strategy' => fake()->randomElement([Debt::STRATEGY_SAC, Debt::STRATEGY_PRICE]),
            'currency' => 'BRL',
            'notes' => null,
            'is_paid_off' => false,
            'paid_off_at' => null,
        ];
    }

    public function paidOff(): static
    {
        return $this->state(fn () => [
            'is_paid_off' => true,
            'total_balance_cents' => 0,
            'paid_off_at' => now(),
        ]);
    }

    public function sac(): static
    {
        return $this->state(fn () => ['payoff_strategy' => Debt::STRATEGY_SAC]);
    }

    public function price(): static
    {
        return $this->state(fn () => ['payoff_strategy' => Debt::STRATEGY_PRICE]);
    }
}
