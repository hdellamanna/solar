<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recurrence\StoreRecurrenceRequest;
use App\Http\Requests\Recurrence\UpdateRecurrenceRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Services\RecurrenceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD operations for the authenticated user's recurring rules.
 */
class RecurrenceController extends Controller
{
    public function __construct(private readonly RecurrenceService $service) {}

    /**
     * Display a paginated list of the user's recurrences.
     */
    public function index(Request $request): Response
    {
        $userId = Auth::id();

        $recurrences = Recurrence::with(['account', 'category'])
            ->where('user_id', $userId)
            ->orderByDesc('active')
            ->orderBy('description')
            ->get()
            ->map(fn (Recurrence $r) => $this->serialize($r));

        return Inertia::render('Recurrences/Index', [
            'recurrences' => $recurrences,
            'accounts' => Account::where('user_id', $userId)->orderBy('name')->get(),
            'categories' => Category::where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })->orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for creating a new recurrence.
     */
    public function create(): Response
    {
        return $this->formProps();
    }

    /**
     * Persist a new recurrence. Amount is provided in reais from the form and
     * stored as cents.
     */
    public function store(StoreRecurrenceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['amount_cents'] = (int) $data['amount_cents'];
        $data['active'] = $request->boolean('active', true);
        $data['category_id'] = $data['category_id'] ?? null;
        $data['ends_at'] = $data['ends_at'] ?? null;
        $data['last_generated_at'] = null;

        $recurrence = Recurrence::create($data);

        return redirect()->route('recurrences.index')->with('success', 'Recorrência criada.');
    }

    /**
     * Show the form for editing a recurrence.
     */
    public function edit(Recurrence $recurrence): Response
    {
        abort_unless($recurrence->user_id === Auth::id(), 403);
        return $this->formProps($recurrence);
    }

    /**
     * Update an existing recurrence.
     */
    public function update(UpdateRecurrenceRequest $request, Recurrence $recurrence): RedirectResponse
    {
        abort_unless($recurrence->user_id === Auth::id(), 403);
        $data = $request->validated();
        $data['amount_cents'] = (int) $data['amount_cents'];
        $data['active'] = $request->boolean('active', true);
        $data['category_id'] = $data['category_id'] ?? null;
        $data['ends_at'] = $data['ends_at'] ?? null;

        $recurrence->update($data);

        return redirect()->route('recurrences.index')->with('success', 'Recorrência atualizada.');
    }

    /**
     * Soft-delete a recurrence.
     */
    public function destroy(Recurrence $recurrence): RedirectResponse
    {
        abort_unless($recurrence->user_id === Auth::id(), 403);
        $recurrence->delete();
        return redirect()->route('recurrences.index')->with('success', 'Recorrência removida.');
    }

    /**
     * Generate any pending transactions for this recurrence up to today.
     */
    public function generateNow(Recurrence $recurrence): RedirectResponse
    {
        abort_unless($recurrence->user_id === Auth::id(), 403);
        $count = $this->service->generateFor($recurrence, CarbonImmutable::today());
        $msg = $count > 0
            ? "{$count} transação(ões) gerada(s) a partir desta recorrência."
            : 'Nenhuma transação nova a gerar.';
        return back()->with('success', $msg);
    }

    /**
     * Shared props for create/edit views.
     */
    private function formProps(?Recurrence $recurrence = null): Response
    {
        $userId = Auth::id();
        return Inertia::render('Recurrences/Form', [
            'recurrence' => $recurrence ? $this->serialize($recurrence) : null,
            'accounts' => Account::where('user_id', $userId)->orderBy('name')->get(),
            'categories' => Category::where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })->orderBy('name')->get(),
        ]);
    }

    /**
     * Project a recurrence to a JSON-friendly array.
     */
    private function serialize(Recurrence $r): array
    {
        return [
            'id' => $r->id,
            'description' => $r->description,
            'amount_cents' => $r->amount_cents,
            'amount_decimal' => $r->amount_decimal,
            'type' => $r->type,
            'frequency' => $r->frequency,
            'human_frequency' => $r->human_frequency,
            'account_id' => $r->account_id,
            'category_id' => $r->category_id,
            'starts_at' => $r->starts_at?->toDateString(),
            'ends_at' => $r->ends_at?->toDateString(),
            'last_generated_at' => $r->last_generated_at?->toDateString(),
            'next_run_at' => $r->next_run_at->toDateString(),
            'active' => (bool) $r->active,
            'account' => $r->relationLoaded('account') ? $r->account : null,
            'category' => $r->relationLoaded('category') ? $r->category : null,
        ];
    }
}
