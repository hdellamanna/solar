<?php

namespace App\Http\Controllers;

use App\Http\Requests\Goal\ContributeGoalRequest;
use App\Http\Requests\Goal\StoreGoalRequest;
use App\Http\Requests\Goal\UpdateGoalRequest;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD + contribute/withdraw for the authenticated user's savings goals (FASE 4A).
 */
class GoalController extends Controller
{
    /** Color palette offered in the form (matches Account/Budget conventions). */
    private const COLORS = [
        '#10b981', '#22c55e', '#84cc16', '#f59e0b', '#eab308',
        '#ef4444', '#dc2626', '#3b82f6', '#6366f1', '#8b5cf6',
        '#ec4899', '#06b6d4',
    ];

    /**
     * Display a listing of the user's active goals (achieved & archived toggleable).
     */
    public function index(Request $request): Response
    {
        $userId = Auth::id();
        $showAchieved = $request->boolean('achieved');
        $showArchived = $request->boolean('archived');

        $query = Goal::where('user_id', $userId)
            ->orderByRaw('achieved_at IS NOT NULL') // in-progress first
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END')
            ->orderBy('deadline')
            ->orderByDesc('created_at');

        if (!$showAchieved) {
            $query->inProgress();
        }
        if (!$showArchived) {
            $query->active();
        }

        $goals = $query->get()->map(fn (Goal $g) => $this->serialize($g));

        $totals = [
            'target_cents' => (int) $goals->sum('target_amount_cents'),
            'current_cents' => (int) $goals->sum('current_amount_cents'),
            'goals_count' => $goals->count(),
        ];
        $totals['overall_progress_percent'] = $totals['target_cents'] > 0
            ? round(min(100, ($totals['current_cents'] / $totals['target_cents']) * 100), 1)
            : 0.0;

        return Inertia::render('Goals/Index', [
            'goals' => $goals,
            'totals' => $totals,
            'filters' => [
                'achieved' => $showAchieved,
                'archived' => $showArchived,
            ],
        ]);
    }

    /**
     * Show the form for creating a new goal.
     */
    public function create(): Response
    {
        return Inertia::render('Goals/Create', [
            'colors' => self::COLORS,
        ]);
    }

    /**
     * Persist a new goal for the current user.
     */
    public function store(StoreGoalRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $targetCents = (int) round(((float) $data['target_amount']) * 100);

        Goal::create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'target_amount_cents' => $targetCents,
            'current_amount_cents' => 0,
            'deadline' => $data['deadline'] ?? null,
            'icon' => $data['icon'] ?? '🎯',
            'color' => $data['color'] ?? '#f59e0b',
        ]);

        return redirect()->route('goals.index')->with('success', 'Meta criada. Bora alcançar!');
    }

    /**
     * Show the form for editing the specified goal.
     */
    public function edit(Goal $goal): Response
    {
        $this->authorizeOwner($goal);

        return Inertia::render('Goals/Edit', [
            'goal' => $this->serialize($goal),
            'colors' => self::COLORS,
        ]);
    }

    /**
     * Update the specified goal in storage.
     */
    public function update(UpdateGoalRequest $request, Goal $goal): RedirectResponse
    {
        $this->authorizeOwner($goal);
        $data = $request->validated();
        $targetCents = (int) round(((float) $data['target_amount']) * 100);

        $goal->update([
            'name' => $data['name'],
            'target_amount_cents' => $targetCents,
            'deadline' => $data['deadline'] ?? null,
            'icon' => $data['icon'] ?? '🎯',
            'color' => $data['color'] ?? '#f59e0b',
        ]);

        // If target was lowered below current, re-evaluate achieved status.
        $goal->markAchievedIfReady();
        $goal->save();

        return redirect()->route('goals.index')->with('success', 'Meta atualizada.');
    }

    /**
     * Soft-archive the goal (kept for history; removed from default lists).
     */
    public function destroy(Goal $goal): RedirectResponse
    {
        $this->authorizeOwner($goal);
        $goal->archive();
        return redirect()->route('goals.index')->with('success', 'Meta arquivada.');
    }

    /**
     * Add a positive amount (in cents) to the goal's running balance.
     */
    public function contribute(ContributeGoalRequest $request, Goal $goal): RedirectResponse
    {
        $this->authorizeOwner($goal);

        $goal->contribute((int) $request->validated('amount_cents'));

        return back()->with('success', 'Valor adicionado à meta.');
    }

    /**
     * Withdraw a positive amount (in cents) from the goal (e.g. spent on the goal).
     */
    public function withdraw(ContributeGoalRequest $request, Goal $goal): RedirectResponse
    {
        $this->authorizeOwner($goal);

        $goal->withdraw((int) $request->validated('amount_cents'));

        return back()->with('success', 'Valor retirado da meta.');
    }

    /**
     * Ensure the goal belongs to the currently authenticated user.
     */
    protected function authorizeOwner(Goal $goal): void
    {
        abort_unless($goal->user_id === Auth::id(), 403);
    }

    /**
     * Serialize a goal with all accessors ready for the frontend.
     *
     * @return array<string, mixed>
     */
    protected function serialize(Goal $g): array
    {
        return [
            'id' => $g->id,
            'name' => $g->name,
            'target_amount_cents' => $g->target_amount_cents,
            'target_decimal' => $g->target_decimal,
            'current_amount_cents' => $g->current_amount_cents,
            'current_decimal' => $g->current_decimal,
            'remaining_cents' => $g->remaining_cents,
            'progress_percent' => $g->progress_percent,
            'is_achieved' => $g->is_achieved,
            'achieved_at' => $g->achieved_at?->toIso8601String(),
            'deadline' => $g->deadline?->toDateString(),
            'days_remaining' => $g->days_remaining,
            'icon' => $g->icon,
            'color' => $g->color,
            'archived_at' => $g->archived_at?->toIso8601String(),
        ];
    }
}
