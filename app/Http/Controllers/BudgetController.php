<?php

namespace App\Http\Controllers;

use App\Http\Requests\Budget\StoreBudgetRequest;
use App\Http\Requests\Budget\UpdateBudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    /**
     * Display a listing of the user's budgets with computed progress.
     */
    public function index(): Response
    {
        $userId = Auth::id();
        $budgets = Budget::with('category')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Budget $b) => $this->serialize($b));

        $totals = [
            'budgeted_cents' => (int) $budgets->sum('amount_cents'),
            'spent_cents' => (int) $budgets->sum('spent_cents'),
            'remaining_cents' => (int) $budgets->sum(fn ($b) => $b['amount_cents'] - $b['spent_cents']),
        ];
        $totals['progress_percent'] = $totals['budgeted_cents'] > 0
            ? round(min(100, ($totals['spent_cents'] / $totals['budgeted_cents']) * 100), 1)
            : 0.0;

        return Inertia::render('Budgets/Index', [
            'budgets' => $budgets,
            'totals' => $totals,
        ]);
    }

    /**
     * Show the form for creating a new budget.
     */
    public function create(): Response
    {
        return Inertia::render('Budgets/Create', $this->formProps());
    }

    /**
     * Persist a new budget for the current user.
     */
    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $amountCents = (int) round(((float) $data['amount']) * 100);

        Budget::create([
            'user_id' => Auth::id(),
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'amount_cents' => $amountCents,
            'period' => $data['period'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'alert_threshold' => $data['alert_threshold'] ?? 80,
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
        ]);

        return redirect()->route('budgets.index')->with('success', 'Orçamento criado.');
    }

    /**
     * Show the form for editing a budget.
     */
    public function edit(Budget $budget): Response
    {
        $this->authorizeOwner($budget);
        return Inertia::render('Budgets/Edit', array_merge(
            $this->formProps(),
            ['budget' => $this->serialize($budget)],
        ));
    }

    /**
     * Update the specified budget.
     */
    public function update(UpdateBudgetRequest $request, Budget $budget): RedirectResponse
    {
        $this->authorizeOwner($budget);
        $data = $request->validated();
        $amountCents = (int) round(((float) $data['amount']) * 100);

        $budget->update([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'amount_cents' => $amountCents,
            'period' => $data['period'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'alert_threshold' => $data['alert_threshold'] ?? 80,
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
        ]);

        return redirect()->route('budgets.index')->with('success', 'Orçamento atualizado.');
    }

    /**
     * Soft-delete a budget.
     */
    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorizeOwner($budget);
        $budget->delete();
        return redirect()->route('budgets.index')->with('success', 'Orçamento removido.');
    }

    /**
     * Reset a budget: archive the current one and create a fresh one with
     * the same values but starting today.
     */
    public function reset(Budget $budget): RedirectResponse
    {
        $this->authorizeOwner($budget);

        $today = CarbonImmutable::today();
        $new = Budget::create([
            'user_id' => $budget->user_id,
            'category_id' => $budget->category_id,
            'name' => $budget->name,
            'amount_cents' => $budget->amount_cents,
            'period' => $budget->period,
            'starts_at' => $today->toDateString(),
            'ends_at' => null,
            'alert_threshold' => $budget->alert_threshold,
            'color' => $budget->color,
            'icon' => $budget->icon,
        ]);

        return redirect()
            ->route('budgets.edit', $new)
            ->with('success', 'Orçamento reiniciado. Nova vigência criada.');
    }

    /**
     * Ensure the budget belongs to the current user.
     */
    protected function authorizeOwner(Budget $budget): void
    {
        abort_unless($budget->user_id === Auth::id(), 403);
    }

    /**
     * Shared props for the create/edit views.
     *
     * @return array<string, mixed>
     */
    protected function formProps(): array
    {
        $userId = Auth::id();
        $categories = Category::where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        })
            ->where('type', 'expense')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'icon', 'color']);

        return [
            'categories' => $categories,
            'periods' => Budget::PERIODS,
            'colors' => ['#10b981', '#22c55e', '#84cc16', '#f59e0b', '#eab308', '#ef4444', '#dc2626', '#3b82f6', '#6366f1', '#8b5cf6', '#ec4899'],
        ];
    }

    /**
     * Serialize a budget with all accessors ready for the frontend.
     *
     * @return array<string, mixed>
     */
    protected function serialize(Budget $b): array
    {
        return [
            'id' => $b->id,
            'name' => $b->name,
            'amount_cents' => $b->amount_cents,
            'amount_decimal' => $b->amount_decimal,
            'period' => $b->period,
            'period_label' => $b->period_label,
            'starts_at' => $b->starts_at?->toDateString(),
            'ends_at' => $b->ends_at?->toDateString(),
            'alert_threshold' => $b->alert_threshold,
            'color' => $b->color,
            'icon' => $b->icon,
            'spent_cents' => $b->spent_cents,
            'remaining_cents' => $b->remaining_cents,
            'progress_percent' => $b->progress_percent,
            'status' => $b->status,
            'days_remaining' => $b->days_remaining,
            'category' => $b->category?->only(['id', 'name', 'icon', 'color']),
        ];
    }
}
