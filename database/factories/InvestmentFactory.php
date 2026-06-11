<?php

namespace Database\Factories;

use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investment>
 */
class InvestmentFactory extends Factory
{
    protected $model = Investment::class;

    public function definition(): array
    {
        $type = fake()->randomElement(array_keys(Investment::TYPES));
        $avg  = fake()->numberBetween(500, 50_000);
        // ~60% of the time the user has marked a current price.
        $hasCurrent = fake()->boolean(60);
        $current    = $hasCurrent ? (int) round($avg * fake()->randomFloat(2, 0.7, 1.4)) : null;

        return [
            'user_id'             => User::factory(),
            'name'                => fake()->randomElement([
                'Petrobras', 'Vale', 'Itausa', 'Magazine Luiza', 'B3',
                'HASH11', 'IVVB11', 'MXRF11', 'XPML11',
                'Bitcoin', 'Ethereum', 'Solana',
                'Tesouro Selic 2029', 'Tesouro IPCA+ 2035', 'Tesouro Prefixado 2030',
                'CDB Banco Inter', 'LCI Caixa', 'LCA Itaú',
            ]),
            'type'                => $type,
            'ticker'              => fake()->boolean(70) ? strtoupper(fake()->lexify('????').fake()->numberBetween(1, 11)) : null,
            'quantity'            => (float) fake()->randomFloat(6, 0.01, 1000),
            'average_price_cents' => $avg,
            'current_price_cents' => $current,
            'currency'            => 'BRL',
            'acquired_at'         => fake()->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d'),
            'notes'               => null,
        ];
    }

    public function stock(): static
    {
        return $this->state(fn () => ['type' => Investment::TYPE_STOCK]);
    }

    public function crypto(): static
    {
        return $this->state(fn () => ['type' => Investment::TYPE_CRYPTO]);
    }

    public function fixedIncome(): static
    {
        return $this->state(fn () => ['type' => Investment::TYPE_FIXED_INCOME]);
    }

    public function treasury(): static
    {
        return $this->state(fn () => ['type' => Investment::TYPE_TREASURY]);
    }

    public function fund(): static
    {
        return $this->state(fn () => ['type' => Investment::TYPE_FUND]);
    }
}
