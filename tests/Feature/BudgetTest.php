<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithCategory(string $categoryName = 'Alimentação'): array
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Carteira',
            'type' => 'cash',
            'currency' => 'BRL',
            'initial_balance_cents' => 0,
            'archived' => false,
        ]);
        $category = Category::firstOrCreate(
            ['name' => $categoryName, 'user_id' => null],
            ['type' => 'expense', 'icon' => '🍔', 'color' => '#f59e0b', 'is_default' => true],
        );
        return [$user, $account, $category];
    }

    public function test_user_can_create_budget(): void
    {
        [$user, , $category] = $this->makeUserWithCategory();

        $this->actingAs($user)
            ->post(route('budgets.store'), [
                'name' => 'Alimentação mensal',
                'category_id' => $category->id,
                'amount' => '800.00',
                'period' => 'monthly',
                'starts_at' => now()->toDateString(),
                'alert_threshold' => 80,
                'color' => '#10b981',
                'icon' => '🍔',
            ])
            ->assertRedirect(route('budgets.index'));

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'name' => 'Alimentação mensal',
            'amount_cents' => 80000,
            'period' => 'monthly',
        ]);
    }

    public function test_spent_cents_sums_expense_transactions_in_category(): void
    {
        [$user, $account, $category] = $this->makeUserWithCategory();

        $budget = Budget::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Alimentação',
            'amount_cents' => 80000,
            'period' => 'monthly',
            'starts_at' => CarbonImmutable::today()->startOfMonth()->toDateString(),
            'alert_threshold' => 80,
        ]);

        // Create two expenses in the current month.
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount_cents' => -25000,
            'date' => CarbonImmutable::today()->subDays(2)->toDateString(),
            'description' => 'Mercado',
            'status' => 'paid',
        ]);
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount_cents' => -15500,
            'date' => CarbonImmutable::today()->subDays(1)->toDateString(),
            'description' => 'iFood',
            'status' => 'paid',
        ]);

        // Income should NOT count.
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'income',
            'amount_cents' => 50000,
            'date' => CarbonImmutable::today()->toDateString(),
            'description' => 'Reembolso',
            'status' => 'paid',
        ]);

        $this->assertSame(40500, $budget->fresh()->spent_cents);
    }

    public function test_progress_is_zero_when_no_expenses(): void
    {
        [$user, , $category] = $this->makeUserWithCategory();

        $budget = Budget::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Lazer',
            'amount_cents' => 20000,
            'period' => 'monthly',
            'starts_at' => CarbonImmutable::today()->startOfMonth()->toDateString(),
            'alert_threshold' => 80,
        ]);

        $this->assertSame(0, $budget->fresh()->spent_cents);
        $this->assertSame(0.0, $budget->fresh()->progress_percent);
        $this->assertSame('safe', $budget->fresh()->status);
    }

    public function test_status_safe_warning_exceeded(): void
    {
        [$user, $account, $category] = $this->makeUserWithCategory();

        $budget = Budget::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Categoria',
            'amount_cents' => 10000,
            'period' => 'monthly',
            'starts_at' => CarbonImmutable::today()->startOfMonth()->toDateString(),
            'alert_threshold' => 80,
        ]);

        // < 80% -> safe
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id,
            'type' => 'expense', 'amount_cents' => -5000, 'date' => CarbonImmutable::today()->toDateString(),
            'description' => 'parcial', 'status' => 'paid',
        ]);
        $this->assertSame('safe', $budget->fresh()->status);

        // bump to 90% -> warning
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id,
            'type' => 'expense', 'amount_cents' => -4000, 'date' => CarbonImmutable::today()->toDateString(),
            'description' => 'mais', 'status' => 'paid',
        ]);
        $this->assertSame('warning', $budget->fresh()->status);

        // 120% -> exceeded
        Transaction::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id,
            'type' => 'expense', 'amount_cents' => -3000, 'date' => CarbonImmutable::today()->toDateString(),
            'description' => 'estouro', 'status' => 'paid',
        ]);
        $this->assertSame('exceeded', $budget->fresh()->status);
    }

    public function test_reset_creates_new_budget_with_today_start(): void
    {
        [$user, , $category] = $this->makeUserWithCategory();

        $old = Budget::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => 'Mercado',
            'amount_cents' => 50000,
            'period' => 'monthly',
            'starts_at' => CarbonImmutable::today()->subMonths(2)->startOfMonth()->toDateString(),
            'alert_threshold' => 80,
            'color' => '#10b981',
            'icon' => '🍔',
        ]);

        $this->actingAs($user)
            ->post(route('budgets.reset', $old))
            ->assertRedirect();

        $new = Budget::where('user_id', $user->id)
            ->where('id', '!=', $old->id)
            ->first();

        $this->assertNotNull($new);
        $this->assertSame($old->category_id, $new->category_id);
        $this->assertSame($old->amount_cents, $new->amount_cents);
        $this->assertSame($old->period, $new->period);
        $this->assertSame(CarbonImmutable::today()->toDateString(), $new->starts_at->toDateString());
        $this->assertNull($new->ends_at);
    }

    public function test_validation_rejects_nonexistent_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('budgets.store'), [
                'name' => 'X',
                'category_id' => 99999,
                'amount' => '100.00',
                'period' => 'monthly',
                'starts_at' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_validation_rejects_invalid_period(): void
    {
        [$user, , $category] = $this->makeUserWithCategory();

        $this->actingAs($user)
            ->post(route('budgets.store'), [
                'name' => 'X',
                'category_id' => $category->id,
                'amount' => '100.00',
                'period' => 'biweekly',
                'starts_at' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('period');
    }

    public function test_user_cannot_access_another_users_budget(): void
    {
        [, , $category] = $this->makeUserWithCategory();
        $other = User::factory()->create();

        $budget = Budget::create([
            'user_id' => $other->id,
            'category_id' => $category->id,
            'name' => 'Outro',
            'amount_cents' => 10000,
            'period' => 'monthly',
            'starts_at' => CarbonImmutable::today()->toDateString(),
            'alert_threshold' => 80,
        ]);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->get(route('budgets.edit', $budget))
            ->assertForbidden();
    }
}
