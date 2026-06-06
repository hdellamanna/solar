<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the Reports page with aggregated charts.
 *
 * Datasets are cached per (user, period) for 5 minutes via Cache::remember.
 * The cache key is fully scoped to the authenticated user so there is no
 * cross-tenant data leakage.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    /**
     * Resolve the from/to range from the request, defaulting to the current month.
     * Returns ISO date strings (YYYY-MM-DD).
     *
     * @return array{0: string, 1: string}
     */
    private function resolveRange(Request $request): array
    {
        $today = CarbonImmutable::today();
        $defaultFrom = $today->startOfMonth()->toDateString();
        $defaultTo = $today->endOfMonth()->toDateString();

        $from = $request->query('from') ?: $defaultFrom;
        $to = $request->query('to') ?: $defaultTo;

        try {
            $fromDt = CarbonImmutable::parse($from)->toDateString();
        } catch (\Throwable) {
            $fromDt = $defaultFrom;
        }
        try {
            $toDt = CarbonImmutable::parse($to)->toDateString();
        } catch (\Throwable) {
            $toDt = $defaultTo;
        }
        if (strcmp($fromDt, $toDt) > 0) {
            [$fromDt, $toDt] = [$toDt, $fromDt];
        }
        return [$fromDt, $toDt];
    }

    /**
     * Display the Reports page with charts for the given period.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        [$from, $to] = $this->resolveRange($request);

        $cacheKey = sprintf('reports:%d:%s:%s', $user->id, $from, $to);

        $payload = Cache::remember($cacheKey, 300, function () use ($user, $from, $to) {
            $monthly = $this->reports->monthlyFlow($user, 12);
            $categories = $this->reports->categoryBreakdown($user, $from, $to, 10);
            $accounts = $this->reports->accountDistribution($user);
            $daily = $this->reports->dailySpending($user, $from, $to);
            $merchants = $this->reports->topMerchants($user, $from, $to, 10);
            $kpis = $this->reports->kpis($user, $from, $to);

            $days = max(1, (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1);
            $avgDailyExpenseCents = $days > 0 ? (int) round($kpis['expense'] / $days) : 0;

            $topCategory = $categories[0] ?? null;
            $topCategoryName = $topCategory['name'] ?? null;
            $topCategoryCents = $topCategory ? abs($topCategory['value_cents']) : 0;

            return [
                'kpis' => [
                    'income_cents' => $kpis['income'],
                    'expense_cents' => $kpis['expense'],
                    'net_cents' => $kpis['net'],
                    'count' => $kpis['count'],
                    'avg_daily_expense_cents' => $avgDailyExpenseCents,
                    'top_category_name' => $topCategoryName,
                    'top_category_cents' => $topCategoryCents,
                ],
                'monthly' => $monthly,
                'categories' => $categories,
                'accounts' => $accounts,
                'daily' => $daily,
                'merchants' => $merchants,
            ];
        });

        return Inertia::render('Reports/Index', array_merge($payload, [
            'from' => $from,
            'to' => $to,
            'preset' => $request->query('preset', 'this_month'),
        ]));
    }
}
