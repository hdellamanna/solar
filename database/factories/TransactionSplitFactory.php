<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionSplit>
 */
class TransactionSplitFactory extends Factory
{
    protected $model = TransactionSplit::class;

    public function definition(): array
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $account = Account::create([
            'user_id' => $owner->id,
            'name' => 'Conta Teste',
            'type' => 'checking',
            'currency' => 'BRL',
            'initial_balance_cents' => 0,
            'archived' => false,
        ]);
        $tx = Transaction::create([
            'user_id' => $owner->id,
            'account_id' => $account->id,
            'type' => 'expense',
            'amount_cents' => -10000,
            'date' => now()->toDateString(),
            'description' => 'Compra dividida',
            'status' => 'paid',
        ]);
        $category = Category::create([
            'user_id' => null,
            'name' => 'Outros',
            'type' => 'expense',
            'is_default' => true,
        ]);

        return [
            'transaction_id' => $tx->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'paid_by_user_id' => $owner->id,
            'description' => 'Parte do(a) ' . $user->name,
            'amount_cents' => -5000,
            'is_paid' => false,
        ];
    }
}
