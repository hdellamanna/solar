<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionFilterTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): array
    {
        $user = User::factory()->create();
        $acc1 = Account::create([
            'user_id' => $user->id, 'name' => 'Nubank', 'type' => 'checking',
            'currency' => 'BRL', 'initial_balance_cents' => 0, 'archived' => false,
        ]);
        $acc2 = Account::create([
            'user_id' => $user->id, 'name' => 'Itau', 'type' => 'credit_card',
            'currency' => 'BRL', 'initial_balance_cents' => 0, 'archived' => false,
        ]);
        $catFood = Category::firstOrCreate(['name' => 'Alimentação', 'user_id' => null],
            ['type' => 'expense', 'icon' => '🍔', 'color' => '#f59e0b', 'is_default' => true]);
        $catTransp = Category::firstOrCreate(['name' => 'Transporte', 'user_id' => null],
            ['type' => 'expense', 'icon' => '🚗', 'color' => '#10b981', 'is_default' => true]);

        // June 2026 transactions
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $acc1->id, 'category_id' => $catFood->id,
            'type' => 'expense', 'amount_cents' => -5000, 'date' => '2026-06-10',
            'description' => 'iFood', 'status' => 'paid',
        ]);
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $acc2->id, 'category_id' => $catTransp->id,
            'type' => 'expense', 'amount_cents' => -3000, 'date' => '2026-06-15',
            'description' => 'Uber', 'status' => 'paid',
        ]);
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $acc1->id, 'category_id' => null,
            'type' => 'income', 'amount_cents' => 100000, 'date' => '2026-06-05',
            'description' => 'Salario', 'status' => 'paid',
        ]);
        // May 2026
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $acc1->id, 'category_id' => $catFood->id,
            'type' => 'expense', 'amount_cents' => -2000, 'date' => '2026-05-12',
            'description' => 'Mercado', 'status' => 'paid',
        ]);

        return [$user, $acc1, $acc2, $catFood, $catTransp];
    }

    public function test_index_default_shows_all_user_transactions(): void
    {
        [$user] = $this->seedData();
        $this->actingAs($user)->get('/transactions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data', 4)
            );
    }

    public function test_filter_by_period(): void
    {
        [$user] = $this->seedData();
        $this->actingAs($user)
            ->get('/transactions?period=this_month')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data', 3) // 3 in June
            );
    }

    public function test_filter_by_type_expense(): void
    {
        [$user] = $this->seedData();
        $this->actingAs($user)
            ->get('/transactions?type=expense')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data', 3)
            );
    }

    public function test_filter_by_account(): void
    {
        [$user] = $this->seedData();
        $nubank = Account::where('user_id', $user->id)->where('name', 'Nubank')->first();
        $this->actingAs($user)
            ->get("/transactions?account_ids[]={$nubank->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data', 3) // 3 transactions in Nubank
            );
    }

    public function test_filter_combined_period_and_type(): void
    {
        [$user] = $this->seedData();
        $this->actingAs($user)
            ->get('/transactions?period=this_month&type=expense')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data', 2) // 2 expenses in June
            );
    }

    public function test_filters_isolated_between_users(): void
    {
        [$user] = $this->seedData();
        $other = User::factory()->create();
        $this->actingAs($other)->get('/transactions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data', 0)
            );
    }
}
