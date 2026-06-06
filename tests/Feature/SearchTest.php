<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithData(): array
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Nubank Principal',
            'type' => 'checking',
            'currency' => 'BRL',
            'initial_balance_cents' => 0,
            'archived' => false,
        ]);
        $category = Category::firstOrCreate(
            ['name' => 'Alimentação', 'user_id' => null],
            ['type' => 'expense', 'icon' => '🍔', 'color' => '#f59e0b', 'is_default' => true],
        );

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount_cents' => -4500, // R$ 45,00
            'date' => '2026-06-10',
            'description' => 'iFood almoco',
            'notes' => 'Pizza com amigos',
            'status' => 'paid',
        ]);
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount_cents' => -1500,
            'date' => '2026-06-12',
            'description' => 'Cafe da tarde',
            'status' => 'paid',
        ]);

        return [$user, $account, $category];
    }

    public function test_search_finds_by_description(): void
    {
        [$user] = $this->makeUserWithData();

        $this->actingAs($user)
            ->getJson('/api/search?q=iFood')
            ->assertOk()
            ->assertJsonPath('transactions.0.description', 'iFood almoco');
    }

    public function test_search_finds_by_notes(): void
    {
        [$user] = $this->makeUserWithData();

        $this->actingAs($user)
            ->getJson('/api/search?q=Pizza')
            ->assertOk()
            ->assertJsonPath('transactions.0.description', 'iFood almoco');
    }

    public function test_search_finds_by_amount_in_reais(): void
    {
        [$user] = $this->makeUserWithData();

        // Search "45" should match 4500 cents (= R$ 45,00)
        $this->actingAs($user)
            ->getJson('/api/search?q=45')
            ->assertOk()
            ->assertJsonCount(1, 'transactions');
    }

    public function test_search_requires_min_2_chars(): void
    {
        [$user] = $this->makeUserWithData();

        $this->actingAs($user)
            ->getJson('/api/search?q=a')
            ->assertOk()
            ->assertJsonCount(0, 'transactions');
    }

    public function test_search_isolated_per_user(): void
    {
        [$user, ] = $this->makeUserWithData();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->getJson('/api/search?q=iFood')
            ->assertOk()
            ->assertJsonCount(0, 'transactions');
    }

    public function test_search_returns_accounts(): void
    {
        [$user, $account] = $this->makeUserWithData();

        $this->actingAs($user)
            ->getJson('/api/search?q=Nubank')
            ->assertOk()
            ->assertJsonPath('accounts.0.name', 'Nubank Principal');
    }
}
