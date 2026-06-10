<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the dashboard's "Fluxo de caixa" line chart.
 *
 * Fixes #5: previously the dashboard rendered an SVG placeholder in the chart
 * card; now the data is shipped from the controller and rendered by ApexCharts.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_ships_six_months_of_cash_flow_data(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCat = Category::factory()->forUser($user)->income()->create();
        $expenseCat = Category::factory()->forUser($user)->expense()->create();

        // Seed 6 months of paid transactions (income +1000, expense -500 each).
        $income = 100_000;   // R$ 1.000,00
        $expense = -50_000;  // -R$ 500,00
        for ($i = 0; $i < 6; $i++) {
            $date = CarbonImmutable::today()->subMonths($i)->startOfMonth()->addDays(5)->toDateString();
            Transaction::factory()->for($user)->for($account)->for($incomeCat)->create([
                'type' => 'income',
                'amount_cents' => $income,
                'date' => $date,
                'status' => 'paid',
            ]);
            Transaction::factory()->for($user)->for($account)->for($expenseCat)->create([
                'type' => 'expense',
                'amount_cents' => $expense,
                'date' => $date,
                'status' => 'paid',
            ]);
        }

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('monthlyFlow', 6)
            // Last (oldest) bucket in the series is from 5 months ago.
            ->where('monthlyFlow.5.income', $income)
            ->where('monthlyFlow.5.expense', $expense)
            ->where('monthlyFlow.5.net', $income + $expense)
        );
    }

    public function test_dashboard_returns_six_empty_buckets_for_new_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('monthlyFlow', 6)
            ->where('monthlyFlow.0.income', 0)
            ->where('monthlyFlow.0.expense', 0)
            ->where('monthlyFlow.0.net', 0)
        );
    }

    public function test_dashboard_excludes_pending_transactions_from_chart(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCat = Category::factory()->forUser($user)->income()->create();

        Transaction::factory()->for($user)->for($account)->for($incomeCat)->create([
            'type' => 'income',
            'amount_cents' => 100_000,
            'date' => CarbonImmutable::today()->toDateString(),
            'status' => 'pending', // not counted in monthlyFlow
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('monthlyFlow.0.income', 0)
        );
    }
}
