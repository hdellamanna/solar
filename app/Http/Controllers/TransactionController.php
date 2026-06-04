<?php

namespace App\Http\Controllers;

use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Display a paginated list of transactions with optional filters.
     */
    public function index(Request $request): Response
    {
        $userId = auth()->id();
        $query = Transaction::with(['account', 'destinationAccount', 'category'])
            ->where('user_id', $userId)
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', $term)
                  ->orWhere('notes', 'like', $term);
            });
        }
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->integer('account_id'));
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        return Inertia::render('Transactions/Index', [
            'transactions' => $query->paginate(25)->withQueryString(),
            'accounts' => Account::where('user_id', $userId)->orderBy('name')->get(),
            'categories' => Category::where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })->orderBy('name')->get(),
            'filters' => $request->only(['search', 'account_id', 'category_id', 'type']),
        ]);
    }

    /**
     * Show the form for creating a new transaction.
     */
    public function create(): Response
    {
        return $this->formProps();
    }

    /**
     * Store a new transaction. Amount is stored in cents (integer).
     * For transfers, two sides are written: an expense on the source and
     * an income on the destination, sharing the same recurrence.
     */
    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $amountCents = (int) round(((float) $data['amount']) * 100);
        // Expense: stored as negative (outflow from account_id).
        // Income: stored as positive (inflow to account_id).
        // Transfer: stored as negative (outflow from source); the destination's
        //   balance accessor counts it again as +inflow via destinationTransactions.
        $signed = match ($data['type']) {
            'expense', 'transfer' => -$amountCents,
            default => $amountCents,
        };

        $userId = auth()->id();
        $tx = Transaction::create([
            'user_id' => $userId,
            'account_id' => $data['account_id'],
            'destination_account_id' => $data['destination_account_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'type' => $data['type'],
            'amount_cents' => $signed,
            'date' => $data['date'],
            'description' => $data['description'],
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'paid',
            'is_pix' => $request->boolean('is_pix'),
            'pix_key' => $data['pix_key'] ?? null,
        ]);

        return redirect()->route('transactions.index')->with('success', 'Transação criada.');
    }

    /**
     * Show the form for editing a transaction.
     */
    public function edit(Transaction $transaction): Response
    {
        abort_unless($transaction->user_id === auth()->id(), 403);
        return $this->formProps($transaction);
    }

    /**
     * Update an existing transaction.
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->user_id === auth()->id(), 403);
        $data = $request->validated();
        $amountCents = (int) round(((float) $data['amount']) * 100);
        $signed = match ($data['type']) {
            'expense', 'transfer' => -$amountCents,
            default => $amountCents,
        };

        $transaction->update([
            'account_id' => $data['account_id'],
            'destination_account_id' => $data['destination_account_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'type' => $data['type'],
            'amount_cents' => $signed,
            'date' => $data['date'],
            'description' => $data['description'],
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'paid',
            'is_pix' => $request->boolean('is_pix'),
            'pix_key' => $data['pix_key'] ?? null,
        ]);

        return redirect()->route('transactions.index')->with('success', 'Transação atualizada.');
    }

    /**
     * Soft-delete a transaction.
     */
    public function destroy(Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->user_id === auth()->id(), 403);
        $transaction->delete();
        return redirect()->route('transactions.index')->with('success', 'Transação removida.');
    }

    /**
     * Shared props for create/edit views.
     */
    private function formProps(?Transaction $transaction = null): Response
    {
        $userId = auth()->id();
        return Inertia::render('Transactions/Form', [
            'transaction' => $transaction,
            'accounts' => Account::where('user_id', $userId)->orderBy('name')->get(),
            'categories' => Category::where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })->orderBy('name')->get(),
        ]);
    }
}
