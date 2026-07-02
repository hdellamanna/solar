<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeAccount(User $user, string $name = 'Nubank', string $type = 'checking', int $initial = 100000): Account
    {
        return Account::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => $type,
            'currency' => 'BRL',
            'initial_balance_cents' => $initial,
            'archived' => false,
        ]);
    }

    private function makeCategory(User $user, string $name, string $type = 'expense', string $color = '#ef4444'): Category
    {
        return Category::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => $type,
            'color' => $color,
            'is_default' => false,
        ]);
    }

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Index')
                ->has('kpis')
                ->has('monthly')
                ->has('categories')
                ->has('accounts')
                ->has('daily')
                ->has('merchants')
                ->has('from')
                ->has('to')
            );
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_monthly_flow_returns_12_months_with_three_populated(): void
    {
        $user = $this->makeUser();
        $account = $this->makeAccount($user);
        $salary = $this->makeCategory($user, 'Salário', 'income', '#10b981');
        $food = $this->makeCategory($user, 'Alimentação', 'expense', '#f59e0b');

        $today = CarbonImmutable::today();
        // Income/expense in current month and 2 months ago
        foreach ([0, 2] as $offset) {
            $base = $today->subMonths($offset);
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $salary->id,
                'type' => 'income',
                'amount_cents' => 500000,
                'date' => $base->day(5)->toDateString(),
                'description' => 'Salário',
                'status' => 'paid',
            ]);
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $food->id,
                'type' => 'expense',
                'amount_cents' => -120000,
                'date' => $base->day(10)->toDateString(),
                'description' => 'Mercado',
                'status' => 'paid',
            ]);
        }

        /** @var ReportService $svc */
        $svc = app(ReportService::class);
        $flow = $svc->monthlyFlow($user, 12);

        $this->assertCount(12, $flow);
        $nonEmpty = array_filter($flow, fn ($m) => $m['income'] > 0 || $m['expense'] < 0);
        $this->assertCount(2, $nonEmpty);

        $current = $flow[11];
        $this->assertSame(500000, $current['income']);
        $this->assertSame(-120000, $current['expense']);
        $this->assertSame(380000, $current['net']);
    }

    public function test_category_breakdown_returns_top_10_ordered_desc_and_with_percent(): void
    {
        $user = $this->makeUser();
        $account = $this->makeAccount($user);

        // Create 12 categories, each with one transaction of varying value
        for ($i = 1; $i <= 12; $i++) {
            $cat = $this->makeCategory($user, "Cat {$i}", 'expense', "#abcdef");
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $cat->id,
                'type' => 'expense',
                'amount_cents' => -($i * 1000),
                'date' => '2026-06-01',
                'description' => 'item',
                'status' => 'paid',
            ]);
        }

        $svc = app(ReportService::class);
        $breakdown = $svc->categoryBreakdown($user, '2026-06-01', '2026-06-30', 10);

        $this->assertCount(10, $breakdown);
        // Ordered by absolute value desc → Cat 12 first
        $this->assertSame('Cat 12', $breakdown[0]['name']);
        $this->assertSame(-12000, $breakdown[0]['value_cents']);
        // Percent values sum to ≤ 100 (we only return top 10, so it can be < 100)
        $sumPct = array_sum(array_column($breakdown, 'percent'));
        $this->assertGreaterThan(0, $sumPct);
        $this->assertLessThanOrEqual(100, $sumPct);
        // Color is present and is a hex
        $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $breakdown[0]['color']);
    }

    public function test_account_distribution_includes_balance_and_color(): void
    {
        $user = $this->makeUser();
        $acc1 = $this->makeAccount($user, 'Nubank', 'checking', 100000);
        $acc2 = $this->makeAccount($user, 'Itaú', 'credit_card', 0);

        // Add a paid transaction so balance is non-zero
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $acc1->id,
            'category_id' => null,
            'type' => 'income',
            'amount_cents' => 50000,
            'date' => '2026-06-15',
            'description' => 'Pix',
            'status' => 'paid',
        ]);

        $svc = app(ReportService::class);
        $dist = $svc->accountDistribution($user);

        $this->assertCount(2, $dist);
        $names = array_column($dist, 'account_name');
        $this->assertContains('Nubank', $names);
        $this->assertContains('Itaú', $names);

        $nubank = collect($dist)->firstWhere('account_name', 'Nubank');
        $this->assertSame(150000, $nubank['balance_cents']);
        $this->assertSame('checking', $nubank['type']);
        $this->assertNotEmpty($nubank['color']);
    }

    public function test_daily_spending_fills_gaps_with_zero(): void
    {
        $user = $this->makeUser();
        $account = $this->makeAccount($user);

        // Only one transaction in the middle of a 5-day window
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => null,
            'type' => 'expense',
            'amount_cents' => -7500,
            'date' => '2026-06-03',
            'description' => 'Café',
            'status' => 'paid',
        ]);

        $svc = app(ReportService::class);
        $daily = $svc->dailySpending($user, '2026-06-01', '2026-06-05');

        $this->assertCount(5, $daily);
        $this->assertSame(0, $daily[0]['value_cents']);
        $this->assertSame(0, $daily[1]['value_cents']);
        $this->assertSame(7500, $daily[2]['value_cents']);
        $this->assertSame(0, $daily[3]['value_cents']);
        $this->assertSame(0, $daily[4]['value_cents']);
    }

    public function test_top_merchants_groups_case_insensitive(): void
    {
        $user = $this->makeUser();
        $account = $this->makeAccount($user);

        // Different capitalizations of the same merchant
        $rows = [
            ['IFood - Almoço',     -2500],
            ['IFOOD - JANTAR',     -4500],
            ['ifood - café',       -1500],
            ['Uber',               -3200],
            ['UBER',               -2800],
            ['Netflix',            -5599],
        ];
        foreach ($rows as [$desc, $amount]) {
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => null,
                'type' => 'expense',
                'amount_cents' => $amount,
                'date' => '2026-06-10',
                'description' => $desc,
                'status' => 'paid',
            ]);
        }

        $svc = app(ReportService::class);
        $merchants = $svc->topMerchants($user, '2026-06-01', '2026-06-30', 10);

        // Should group all "ifood" into one row, all "uber" into another
        $this->assertCount(3, $merchants);
        $byDesc = collect($merchants)->keyBy(fn ($m) => strtolower($m['description']));

        // iFood total = 2500 + 4500 + 1500 = 8500, count = 3
        $ifood = $byDesc->first(fn ($_, $k) => str_contains($k, 'ifood'));
        $this->assertSame(-8500, $ifood['total_cents']);
        $this->assertSame(3, $ifood['count']);
    }

    public function test_user_isolation_in_reports(): void
    {
        $alice = $this->makeUser();
        $bob = $this->makeUser();
        $aliceAcc = $this->makeAccount($alice, 'Alice Bank');
        $bobAcc = $this->makeAccount($bob, 'Bob Bank');

        // Anchor on the current month — kpis.income_cents is the current
        // month's income total, so transactions must be in this month.
        $currentMonth = \Carbon\CarbonImmutable::today()->startOfMonth()->addDays(4);

        Transaction::create([
            'user_id' => $alice->id,
            'account_id' => $aliceAcc->id,
            'category_id' => null,
            'type' => 'income',
            'amount_cents' => 999900,
            'date' => $currentMonth->toDateString(),
            'description' => 'Alice salary',
            'status' => 'paid',
        ]);
        Transaction::create([
            'user_id' => $bob->id,
            'account_id' => $bobAcc->id,
            'category_id' => null,
            'type' => 'income',
            'amount_cents' => 50000,
            'date' => $currentMonth->toDateString(),
            'description' => 'Bob income',
            'status' => 'paid',
        ]);

        // Authenticated as Alice
        $response = $this->actingAs($alice)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('kpis.income_cents', 999900)
                ->where('accounts.0.account_name', 'Alice Bank')
            );

        // Authenticated as Bob
        $this->actingAs($bob)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('kpis.income_cents', 50000)
                ->where('accounts.0.account_name', 'Bob Bank')
            );
    }
}
