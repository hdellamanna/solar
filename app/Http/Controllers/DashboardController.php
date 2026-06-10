<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\Goal;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\FxRateService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the main dashboard with aggregated metrics and recent transactions.
 *
 * FASE 6A — multi-currency: every "total balance" figure on the
 * dashboard is converted to the user's `home_currency` via
 * {@see FxRateService} (frankfurter.app, ECB rates, cached 12h).
 * Per-account balances are still shown in their native currency on
 * the accounts list — only the aggregated totals are converted.
 */
class DashboardController extends Controller
{
    private const FLOW_MONTHS = 6;

    public function __construct(
        private readonly ReportService $reports,
        private readonly FxRateService $fx,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $home = $user->home_currency;
        $today = CarbonImmutable::today();
        $startOfMonth = $today->startOfMonth()->toDateString();
        $endOfMonth = $today->endOfMonth()->toDateString();

        // Total balance across all non-archived accounts, converted to home_currency.
        $accounts = $user->accounts()->active()->with(['transactions', 'balances'])->get();
        $totalBalanceCents = (int) $accounts->sum(fn (Account $a) => $this->balanceInHomeCents($a, $home));

        // Month inflow / outflow — already in account currency; for
        // mixed-currency accounts we use the per-transaction FX
        // snapshot (or 1.0 when none, which means same currency).
        $monthInflowCents = (int) $user->transactions()
            ->where('type', 'income')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get(['amount_cents', 'currency', 'exchange_rate_cents'])
            ->sum(fn (Transaction $t) => $this->txInHomeCents($t, $home));
        $monthOutflowCents = (int) $user->transactions()
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get(['amount_cents', 'currency', 'exchange_rate_cents'])
            ->sum(fn (Transaction $t) => $this->txInHomeCents($t, $home));

        $monthSavingsCents = $monthInflowCents + $monthOutflowCents;

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
                'currency' => $t->currency,
                'exchange_rate_cents' => $t->exchange_rate_cents,
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
            'currency' => $a->currency,
            'is_multi_currency' => $a->is_multi_currency,
            'balance_cents' => $a->balance_cents,
            'balances' => $a->balances->map(fn (AccountBalance $b) => [
                'currency' => $b->currency,
                'balance_cents' => (int) $b->balance_cents,
            ])->values()->all(),
        ]);

        $monthlyFlow = $this->reports->monthlyFlow($user, self::FLOW_MONTHS);

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

        $activeSubs = Subscription::where('user_id', $user->id)->active()->get();
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
            'homeCurrency' => $home,
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

    /**
     * Convert an account's full balance to `homeCents`. For a
     * multi-currency account, the home balance plus every sub-balance
     * is converted individually and summed.
     */
    private function balanceInHomeCents(Account $a, string $home): int
    {
        $total = 0;
        $total += $this->convertCents($a->balance_cents, $a->home_currency, $home);
        foreach ($a->balances as $b) {
            $total += $this->convertCents($b->balance_cents, $b->currency, $home);
        }
        return (int) $total;
    }

    /**
     * Convert a transaction's amount to home cents, using the FX
     * snapshot the controller stored at creation time when one is
     * available, and a fresh rate lookup as a fallback.
     */
    private function txInHomeCents(Transaction $t, string $home): int
    {
        $txCurrency = $t->currency ?? $home;
        if ($txCurrency === $home) {
            return (int) $t->amount_cents;
        }
        $rate = null;
        if ($t->exchange_rate_cents !== null) {
            $rate = $t->exchange_rate_cents / 100;
        } else {
            $rate = $this->fx->rate($txCurrency, $home, $t->date->toDateString());
        }
        if ($rate === null) {
            return (int) $t->amount_cents; // graceful: stay in native currency
        }
        return (int) round($t->amount_cents * $rate);
    }

    private function convertCents(int $cents, string $from, string $to): int
    {
        if ($from === $to) {
            return $cents;
        }
        $rate = $this->fx->rate($from, $to);
        if ($rate === null) {
            return $cents; // graceful fallback
        }
        return (int) round($cents * $rate);
    }
}

