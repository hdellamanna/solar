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

        // Anchor on the current month (so the `period=this_month` filter has
        // data regardless of when the test runs — previously these dates
        // were hardcoded to 2026-06-XX which broke once we crossed into July).
        $currentMonth = now()->startOfMonth();
        $prevMonth = $currentMonth->copy()->subMonth();

        Transaction::create([
            'user_id' => $user->id, 'account_id' => $acc1->id, 'category_id' => $catFood->id,
            'type' => 'expense', 'amount_cents' => -5000, 'date' => $currentMonth->copy()->addDays(4)->toDateString(),
            'description' => 'iFood', 'status' => 'paid',
        ]);
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $acc2->id, 'category_id' => $catTransp->id,
            'type' => 'expense', 'amount_cents' => -3000, 'date' => $currentMonth->copy()->addDays(9)->toDateString(),
            'description' => 'Uber', 'status' => 'paid',
        ]);
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $acc1->id, 'category_id' => null,
            'type' => 'income', 'amount_cents' => 100000, 'date' => $currentMonth->copy()->addDays(2)->toDateString(),
            'description' => 'Salario', 'status' => 'paid',
        ]);
        // Previous month — must NOT appear in this_month filter.
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $acc1->id, 'category_id' => $catFood->id,
            'type' => 'expense', 'amount_cents' => -2000, 'date' => $prevMonth->copy()->addDays(11)->toDateString(),
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
