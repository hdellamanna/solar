<?php

namespace App\Http\Controllers;

use App\Http\Requests\Debt\StoreDebtRequest;
use App\Http\Requests\Debt\UpdateDebtRequest;
use App\Models\Debt;
use App\Services\AmortizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD + simulate + markAsPaidOff for the authenticated user's
 * tracked debts / financing contracts (FASE 5).
 *
 * The "simulate" action delegates to {@see AmortizationService} —
 * a pure-math helper that produces the month-by-month SAC or
 * Price schedule for the modal in the Vue `Show` page. No state
 * is changed by simulate: it's a read-only projection.
 */
class DebtController extends Controller
{
    public function __construct(
        private readonly AmortizationService $amortization,
    ) {
    }

    /**
     * Display a listing of the user's debts (active + paid-off, with toggle).
     */
    public function index(Request $request): Response
    {
        $userId = Auth::id();
        $showPaidOff = $request->boolean('paid_off');

        $query = Debt::with('user')
            ->forUser($userId)
            ->orderByRaw('is_paid_off ASC')           // active first
            ->orderBy('creditor');

        if (!$showPaidOff) {
            $query->active();
        }

        $debts = $query->get()->map(fn (Debt $d) => $this->serialize($d));

        $active = $debts->where('is_paid_off', false);
        $totals = [
            'count_active' => $active->count(),
            'count_paid_off' => $debts->count() - $active->count(),
            'total_balance_cents' => (int) $active->sum('total_balance_cents'),
            'monthly_commitment_cents' => (int) $active->sum('monthly_payment_cents'),
            'weighted_avg_rate' => $this->weightedAvgRate($active),
        ];

        return Inertia::render('Debts/Index', [
            'debts' => $debts,
            'totals' => $totals,
            'filters' => ['paid_off' => $showPaidOff],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Debts/Create', [
            'strategies' => [
                ['id' => Debt::STRATEGY_SAC, 'label' => 'SAC', 'description' => 'Parcelas decrescentes. Amortização constante.'],
                ['id' => Debt::STRATEGY_PRICE, 'label' => 'Price', 'description' => 'Parcelas fixas. Tabela Price.'],
            ],
        ]);
    }

    public function store(StoreDebtRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Debt::create([
            'user_id' => Auth::id(),
            'creditor' => $data['creditor'],
            'description' => $data['description'] ?? null,
            'total_balance_cents' => (int) round(((float) $data['total_balance']) * 100),
            'interest_rate_annual' => round(((float) $data['interest_rate']) / 100, 4),
            'monthly_payment_cents' => (int) round(((float) $data['monthly_payment']) * 100),
            'start_date' => $data['start_date'],
            'payoff_strategy' => $data['payoff_strategy'],
            'currency' => strtoupper($data['currency']),
            'notes' => $data['notes'] ?? null,
            'is_paid_off' => false,
        ]);

        return redirect()->route('debts.index')->with('success', 'Dívida cadastrada.');
    }

    public function show(Debt $debt): Response
    {
        $this->authorizeOwner($debt);

        return Inertia::render('Debts/Show', [
            'debt' => $this->serialize($debt),
        ]);
    }

    public function edit(Debt $debt): Response
    {
        $this->authorizeOwner($debt);

        return Inertia::render('Debts/Edit', [
            'debt' => $this->serialize($debt),
            'strategies' => [
                ['id' => Debt::STRATEGY_SAC, 'label' => 'SAC', 'description' => 'Parcelas decrescentes. Amortização constante.'],
                ['id' => Debt::STRATEGY_PRICE, 'label' => 'Price', 'description' => 'Parcelas fixas. Tabela Price.'],
            ],
        ]);
    }

    public function update(UpdateDebtRequest $request, Debt $debt): RedirectResponse
    {
        $this->authorizeOwner($debt);
        $data = $request->validated();

        $debt->update([
            'creditor' => $data['creditor'],
            'description' => $data['description'] ?? null,
            'total_balance_cents' => (int) round(((float) $data['total_balance']) * 100),
            'interest_rate_annual' => round(((float) $data['interest_rate']) / 100, 4),
            'monthly_payment_cents' => (int) round(((float) $data['monthly_payment']) * 100),
            'start_date' => $data['start_date'],
            'payoff_strategy' => $data['payoff_strategy'],
            'currency' => strtoupper($data['currency']),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('debts.index')->with('success', 'Dívida atualizada.');
    }

    /**
     * Soft-delete the debt (kept for history; excluded from active totals).
     */
    public function destroy(Debt $debt): RedirectResponse
    {
        $this->authorizeOwner($debt);
        $debt->delete();
        return redirect()->route('debts.index')->with('success', 'Dívida removida.');
    }

    /**
     * Build a month-by-month amortization schedule for the given debt
     * using the chosen strategy (or the debt's default). Returns JSON
     * for the "Ver simulação" modal in the Vue `Show` page.
     */
    public function simulate(Request $request, Debt $debt)
    {
        $this->authorizeOwner($debt);

        $strategy = $request->input('strategy', $debt->payoff_strategy);
        if (!in_array($strategy, [Debt::STRATEGY_SAC, Debt::STRATEGY_PRICE], true)) {
            $strategy = $debt->payoff_strategy;
        }

        $result = $this->amortization->simulate(
            (int) $debt->total_balance_cents,
            (float) $debt->interest_rate_annual,
            (int) $debt->monthly_payment_cents,
            $strategy,
            $debt->start_date->toDateString(),
        );

        return response()->json($result);
    }

    /**
     * Mark a debt as paid off (idempotent).
     *
     * Validation: only allowed when `total_balance_cents == 0`
     * (otherwise the simulator would still say "in progress"). The
     * caller is responsible for the UI confirmation — the controller
     * will simply 422 if the rule is violated.
     */
    public function markAsPaidOff(Debt $debt)
    {
        $this->authorizeOwner($debt);

        if ((int) $debt->total_balance_cents > 0) {
            return response()->json([
                'message' => 'Só é possível quitar dívidas com saldo zerado. Atualize o saldo antes.',
            ], 422);
        }

        $debt->markAsPaidOff();

        return back()->with('success', 'Dívida marcada como quitada.');
    }

    /**
     * Ensure the debt belongs to the currently authenticated user.
     */
    protected function authorizeOwner(Debt $d): void
    {
        abort_unless((int) $d->user_id === (int) Auth::id(), 403);
    }

    /**
     * Compute the balance-weighted average annual rate across a
     * collection of serialized debts. Returns 0.0 when the total
     * balance is zero.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $debts
     */
    private function weightedAvgRate($debts): float
    {
        $totalBalance = (int) $debts->sum('total_balance_cents');
        if ($totalBalance <= 0) {
            return 0.0;
        }
        $weighted = 0.0;
        foreach ($debts as $d) {
            $weighted += ((float) $d['interest_rate_annual']) * (int) $d['total_balance_cents'];
        }
        return round($weighted / $totalBalance, 4);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Debt $d): array
    {
        return [
            'id' => $d->id,
            'creditor' => $d->creditor,
            'description' => $d->description,
            'total_balance_cents' => (int) $d->total_balance_cents,
            'total_balance_decimal' => $d->total_balance_decimal,
            'total_balance_formatted' => $d->total_balance_formatted,
            'interest_rate_annual' => (float) $d->interest_rate_annual,
            'interest_rate_percent' => $d->interest_rate_percent,
            'monthly_interest_rate' => $d->monthly_interest_rate,
            'monthly_payment_cents' => (int) $d->monthly_payment_cents,
            'monthly_payment_decimal' => $d->monthly_payment_decimal,
            'monthly_payment_formatted' => $d->monthly_payment_formatted,
            'start_date' => $d->start_date->toDateString(),
            'payoff_strategy' => $d->payoff_strategy,
            'currency' => $d->currency,
            'currency_symbol' => $d->currency_symbol,
            'notes' => $d->notes,
            'is_paid_off' => (bool) $d->is_paid_off,
            'is_settled' => (bool) $d->is_settled,
            'paid_off_at' => $d->paid_off_at?->toIso8601String(),
            'estimated_payoff_months' => $d->estimated_payoff_months,
        ];
    }
}
