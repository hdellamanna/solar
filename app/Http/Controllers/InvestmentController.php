<?php

namespace App\Http\Controllers;

use App\Http\Requests\Investment\StoreInvestmentRequest;
use App\Http\Requests\Investment\UpdateInvestmentRequest;
use App\Models\Investment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD operations for the authenticated user's investment positions (FASE 5).
 *
 * Investments are a separate portfolio tracker — they live outside
 * the `accounts` table on purpose. The user records each asset they
 * hold (stock, fund, crypto, fixed-income, treasury), the quantity
 * they own, the average price they paid, and (optionally) the
 * current market value. P&L is derived from these fields on the
 * fly — see {@see Investment::getProfitLossCentsAttribute()}.
 *
 * The controller is intentionally thin: all the math lives on the
 * model. The controller's job is wiring, validation, serialization,
 * and redirecting with the right flash message.
 */
class InvestmentController extends Controller
{
    /**
     * Display a paginated listing of the user's investments.
     *
     * Sorted by type, then name — keeps the type groups together in
     * the grid for fast scanning.
     */
    public function index(Request $request): Response
    {
        $userId = Auth::id();

        // Aggregate totals from a single, non-paginated query — running the
        // sum over the page would miss the rest of the portfolio.
        $all = Investment::where('user_id', $userId)->get();
        $totalInvested = (int) $all->sum('total_invested_cents');
        $currentValue   = (int) $all->sum('current_value_cents');
        $profitLoss     = (int) ($currentValue - $totalInvested);

        $totals = [
            'count'                => (int) $all->count(),
            'total_invested_cents' => $totalInvested,
            'current_value_cents'  => $currentValue,
            'profit_loss_cents'    => $profitLoss,
            'profit_loss_percent'  => $totalInvested > 0
                ? round(($profitLoss / $totalInvested) * 100, 2)
                : null,
        ];

        $investments = Investment::where('user_id', $userId)
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (Investment $i) => $this->serialize($i));

        return Inertia::render('Investments/Index', [
            'investments' => $investments->items(),
            'pagination'  => [
                'current_page' => $investments->currentPage(),
                'last_page'    => $investments->lastPage(),
                'per_page'     => $investments->perPage(),
                'total'        => $investments->total(),
            ],
            'totals'      => $totals,
            'types'       => Investment::TYPES,
            'typeColors'  => Investment::TYPE_COLORS,
        ]);
    }

    /**
     * Show the form for creating a new investment.
     */
    public function create(): Response
    {
        return Inertia::render('Investments/Create', [
            'types'       => Investment::TYPES,
            'typeColors'  => Investment::TYPE_COLORS,
        ]);
    }

    /**
     * Persist a new investment for the current user.
     *
     * Decimal inputs are converted to cents before storage. `current_price`
     * stays nullable so the user can record a position before knowing the
     * current market value.
     */
    public function store(StoreInvestmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Investment::create([
            'user_id'              => Auth::id(),
            'name'                 => $data['name'],
            'type'                 => $data['type'],
            'ticker'               => $data['ticker'] ?? null,
            'quantity'             => (float) $data['quantity'],
            'average_price_cents'  => $this->toCents($data['average_price']),
            'current_price_cents'  => isset($data['current_price']) && $data['current_price'] !== ''
                ? $this->toCents($data['current_price'])
                : null,
            'currency'             => strtoupper($data['currency']),
            'acquired_at'          => $data['acquired_at'] ?? null,
            'notes'                => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('investments.index')
            ->with('success', 'Investimento criado.');
    }

    /**
     * Show a single investment's detail page.
     */
    public function show(Investment $investment): Response
    {
        $this->authorizeOwner($investment);

        return Inertia::render('Investments/Show', [
            'investment' => $this->serialize($investment, detailed: true),
        ]);
    }

    /**
     * Show the form for editing an existing investment.
     */
    public function edit(Investment $investment): Response
    {
        $this->authorizeOwner($investment);

        return Inertia::render('Investments/Edit', [
            'investment' => $this->serialize($investment, detailed: true),
            'types'      => Investment::TYPES,
            'typeColors' => Investment::TYPE_COLORS,
        ]);
    }

    /**
     * Update the specified investment in storage.
     */
    public function update(UpdateInvestmentRequest $request, Investment $investment): RedirectResponse
    {
        $this->authorizeOwner($investment);
        $data = $request->validated();

        $investment->update([
            'name'                 => $data['name'],
            'type'                 => $data['type'],
            'ticker'               => $data['ticker'] ?? null,
            'quantity'             => (float) $data['quantity'],
            'average_price_cents'  => $this->toCents($data['average_price']),
            'current_price_cents'  => isset($data['current_price']) && $data['current_price'] !== ''
                ? $this->toCents($data['current_price'])
                : null,
            'currency'             => strtoupper($data['currency']),
            'acquired_at'          => $data['acquired_at'] ?? null,
            'notes'                => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('investments.index')
            ->with('success', 'Investimento atualizado.');
    }

    /**
     * Soft-delete the investment (kept in history).
     */
    public function destroy(Investment $investment): RedirectResponse
    {
        $this->authorizeOwner($investment);
        $investment->delete();

        return redirect()
            ->route('investments.index')
            ->with('success', 'Investimento removido.');
    }

    /**
     * Ensure the investment belongs to the currently authenticated user.
     */
    protected function authorizeOwner(Investment $i): void
    {
        abort_unless($i->user_id === Auth::id(), 403);
    }

    /**
     * Convert a decimal currency string to integer cents.
     * Accepts strings ("10.50") or floats (10.5) — same input shape
     * Laravel gives us from the form.
     */
    protected function toCents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    /**
     * Serialize an investment for the frontend.
     *
     * @return array<string, mixed>
     */
    protected function serialize(Investment $i, bool $detailed = false): array
    {
        $base = [
            'id'                   => $i->id,
            'name'                 => $i->name,
            'type'                 => $i->type,
            'type_label'           => $i->type_label,
            'type_color'           => $i->type_color,
            'ticker'               => $i->ticker,
            'quantity'             => (float) $i->quantity,
            'formatted_quantity'   => $i->formatted_quantity,
            'average_price_cents'  => (int) $i->average_price_cents,
            'average_price_decimal' => $i->average_price_decimal,
            'current_price_cents'  => $i->current_price_cents !== null ? (int) $i->current_price_cents : null,
            'current_price_decimal' => $i->current_price_decimal,
            'has_current_price'    => $i->has_current_price,
            'currency'             => $i->currency,
            'currency_symbol'      => $i->currency_symbol,
            'total_invested_cents' => (int) $i->total_invested_cents,
            'current_value_cents'  => (int) $i->current_value_cents,
            'profit_loss_cents'    => (int) $i->profit_loss_cents,
            'profit_loss_percent'  => $i->profit_loss_percent,
            'is_profit'            => $i->is_profit,
            'acquired_at'          => $i->acquired_at?->toDateString(),
            'notes'                => $i->notes,
        ];

        if ($detailed) {
            $base['created_at'] = $i->created_at?->toIso8601String();
            $base['updated_at'] = $i->updated_at?->toIso8601String();
        }

        return $base;
    }
}
