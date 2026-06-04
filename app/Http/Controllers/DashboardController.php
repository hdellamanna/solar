<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the main dashboard with aggregated metrics and recent transactions.
 */
class DashboardController extends Controller
{
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

        return Inertia::render('Dashboard', [
            'totalBalanceCents' => $totalBalanceCents,
            'monthInflowCents' => $monthInflowCents,
            'monthOutflowCents' => $monthOutflowCents,
            'monthSavingsCents' => $monthSavingsCents,
            'recentTransactions' => $recentTransactions,
            'accounts' => $accountsData,
            'accountCount' => $accounts->count(),
        ]);
    }
}
