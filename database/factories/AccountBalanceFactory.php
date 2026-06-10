<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountBalance>
 */
class AccountBalanceFactory extends Factory
{
    protected $model = AccountBalance::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'currency' => 'USD',
            'balance_cents' => fake()->numberBetween(0, 500_000), // 0 - R$ 5.000 / $5.000
        ];
    }
}
