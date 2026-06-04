<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_income_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Nubank',
            'type' => 'checking',
            'currency' => 'BRL',
            'initial_balance_cents' => 0,
            'archived' => false,
        ]);

        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'type' => 'income',
                'account_id' => $account->id,
                'amount' => '1500.00',
                'date' => '2026-06-01',
                'description' => 'Salario',
                'status' => 'paid',
            ])
            ->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'income',
            'amount_cents' => 150000,
        ]);
    }

    public function test_expense_stored_as_negative_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Nubank',
            'type' => 'checking',
            'currency' => 'BRL',
            'initial_balance_cents' => 0,
            'archived' => false,
        ]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'expense',
            'account_id' => $account->id,
            'amount' => '99.90',
            'date' => '2026-06-15',
            'description' => 'Internet',
            'status' => 'paid',
        ])->assertRedirect(route('transactions.index'));

        $this->assertDatabaseHas('transactions', [
            'type' => 'expense',
            'amount_cents' => -9990,
        ]);
    }

    public function test_transfer_moves_balance_between_accounts(): void
    {
        $user = User::factory()->create();
        $origem = Account::create([
            'user_id' => $user->id, 'name' => 'Origem', 'type' => 'checking',
            'currency' => 'BRL', 'initial_balance_cents' => 100000, 'archived' => false,
        ]);
        $destino = Account::create([
            'user_id' => $user->id, 'name' => 'Destino', 'type' => 'savings',
            'currency' => 'BRL', 'initial_balance_cents' => 0, 'archived' => false,
        ]);

        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'transfer',
            'account_id' => $origem->id,
            'destination_account_id' => $destino->id,
            'amount' => '500.00',
            'date' => '2026-06-10',
            'description' => 'Reserva',
            'status' => 'paid',
        ])->assertRedirect(route('transactions.index'));

        // Origem: 1000 - 500 = 500. Destino: 0 + 500 = 500.
        $this->assertSame(50000, $origem->fresh()->balance_cents);
        $this->assertSame(50000, $destino->fresh()->balance_cents);
    }

    public function test_soft_delete_preserves_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id, 'name' => 'X', 'type' => 'cash',
            'currency' => 'BRL', 'initial_balance_cents' => 0, 'archived' => false,
        ]);
        $tx = Transaction::create([
            'user_id' => $user->id, 'account_id' => $account->id,
            'type' => 'expense', 'amount_cents' => -1000,
            'date' => '2026-06-01', 'description' => 'Cafe', 'status' => 'paid',
        ]);

        $this->actingAs($user)->delete(route('transactions.destroy', $tx))
            ->assertRedirect(route('transactions.index'));

        $this->assertSoftDeleted('transactions', ['id' => $tx->id]);
    }
}
