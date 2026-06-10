<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Goal;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the main dashboard with aggregated metrics and recent transactions.
 *
 * The cash-flow chart series (last 6 months) is computed via {@see ReportService::monthlyFlow()}
 * so the same SQL aggregation powers both the dashboard and the Reports page.
 */
class DashboardController extends Controller
{
    /**
     * Number of months to chart in the dashboard "Fluxo de caixa" line.
     * Smaller than the Reports page (12) because the dashboard card is compact.
     */
    private const FLOW_MONTHS = 6;

    public function __construct(private readonly ReportService $reports)
    {
    }

    /**
     * Compute KPIs and return the dashboard view.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $today = CarbonImmutable::today();
        $startOfMonth = $today->startOfMonth()->toDateString();
        $endOfMonth = $today->endOfMonth()->toDateString();

        // Total balance across all non-archived accounts.
        $accounts = $user->accounts()->active()->with('transactions')->get();
        $totalBalanceCents = $accounts->sum(fn (Account $a) => $a->balance_cents);

        // Month inflow (income transactions that already happened or will happen).
        $monthInflowCents = (int) $user->transactions()
            ->where('type', 'income')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount_cents');

        $monthOutflowCents = (int) $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount_cents');

        $monthSavingsCents = $monthInflowCents + $monthOutflowCents; // outflows are negative

        $recentTransactions = $user->transactions()
            ->with(['account', 'category', 'destinationAccount'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount_cents' => $t->amount_cents,
                'amount_decimal' => $t->amount_decimal,
                'date' => $t->date->toDateString(),
                'description' => $t->description,
                'status' => $t->status,
                'account' => $t->account?->only(['id', 'name', 'color', 'icon']),
                'destination_account' => $t->destinationAccount?->only(['id', 'name']),
                'category' => $t->category?->only(['id', 'name', 'color', 'icon']),
            ]);

        $accountsData = $accounts->map(fn (Account $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'type' => $a->type,
            'color' => $a->color,
            'balance_cents' => $a->balance_cents,
        ]);

        // Cash-flow line chart series for the last N months.
        $monthlyFlow = $this->reports->monthlyFlow($user, self::FLOW_MONTHS);

        // Top 3 in-progress goals (closest to deadline first) for the dashboard widget.
        $goals = Goal::where('user_id', $user->id)
            ->active()
            ->inProgress()
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END')
            ->orderBy('deadline')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get()
            ->map(fn (Goal $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'target_amount_cents' => $g->target_amount_cents,
                'current_amount_cents' => $g->current_amount_cents,
                'progress_percent' => $g->progress_percent,
                'remaining_cents' => $g->remaining_cents,
                'deadline' => $g->deadline?->toDateString(),
                'days_remaining' => $g->days_remaining,
                'icon' => $g->icon,
                'color' => $g->color,
            ]);

        // Active subscriptions: total monthly + 3 closest to next billing.
        $activeSubs = Subscription::where('user_id', $user->id)
            ->active()
            ->get();

        $subscriptionsTotalMonthlyCents = (int) $activeSubs->sum('monthly_cents');
        $upcomingSubscriptions = $activeSubs
            ->sortBy('days_until_billing')
            ->take(3)
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'amount_cents' => $s->amount_cents,
                'amount_formatted' => $s->amount_formatted,
                'next_billing_at' => $s->next_billing_at->toDateString(),
                'days_until_billing' => $s->days_until_billing,
                'icon' => $s->icon,
                'color' => $s->color,
            ])
            ->values();

        return Inertia::render('Dashboard', [
            'totalBalanceCents' => $totalBalanceCents,
            'monthInflowCents' => $monthInflowCents,
            'monthOutflowCents' => $monthOutflowCents,
            'monthSavingsCents' => $monthSavingsCents,
            'recentTransactions' => $recentTransactions,
            'accounts' => $accountsData,
            'accountCount' => $accounts->count(),
            'monthlyFlow' => $monthlyFlow,
            'goals' => $goals,
            'subscriptions' => [
                'total_monthly_cents' => $subscriptionsTotalMonthlyCents,
                'active_count' => $activeSubs->count(),
                'upcoming' => $upcomingSubscriptions,
            ],
        ]);
    }
}
