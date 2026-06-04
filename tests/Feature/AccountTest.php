<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('accounts.store'), [
                'name' => 'Nubank Test',
                'type' => 'checking',
                'currency' => 'BRL',
                'color' => '#820ad1',
                'initial_balance_cents' => 100000,
                'archived' => false,
            ])
            ->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => 'Nubank Test',
            'type' => 'checking',
        ]);
    }

    public function test_account_balance_includes_paid_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Conta Test',
            'type' => 'checking',
            'currency' => 'BRL',
            'initial_balance_cents' => 50000, // R$ 500
            'archived' => false,
        ]);

        // Income +R$ 1000
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => 'income',
            'amount_cents' => 100000,
            'date' => '2026-06-01',
            'description' => 'Salario',
            'status' => 'paid',
        ]);
        // Expense -R$ 200
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => 'expense',
            'amount_cents' => -20000,
            'date' => '2026-06-02',
            'description' => 'Mercado',
            'status' => 'paid',
        ]);

        $this->assertSame(130000, $account->fresh()->balance_cents); // 500 + 1000 - 200 = 1300
    }

    public function test_user_cannot_access_other_users_account(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $bobsAccount = Account::create([
            'user_id' => $bob->id,
            'name' => 'Conta do Bob',
            'type' => 'checking',
            'currency' => 'BRL',
            'initial_balance_cents' => 0,
            'archived' => false,
        ]);

        $this->actingAs($alice)
            ->get(route('accounts.edit', $bobsAccount))
            ->assertForbidden();
    }
}
