<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidTransactionSplitException;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use App\Models\User;
use App\Services\FxRateService;
use App\Services\TransactionFilterService;
use App\Services\TransactionSplitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionSplitService $splits,
        private readonly FxRateService $fx,
    ) {}

    /**
     * Display a paginated list of transactions with optional filters.
     */
    public function index(Request $request, TransactionFilterService $filter): Response
    {
        $userId = auth()->id();
        $filters = $filter->validate($request);

        $query = $filter->baseQuery($userId);
        $filter->apply($query, $filters);
        $query->withCount('splits');

        return Inertia::render('Transactions/Index', [
            'transactions' => $query->paginate(25)->withQueryString(),
            'accounts' => Account::where('user_id', $userId)->orderBy('name')->get(),
            'categories' => Category::where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })->orderBy('name')->get(),
            'filters' => $filters,
            'periodPresets' => TransactionFilterService::periodPresets(),
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
        $signed = match ($data['type']) {
            'expense', 'transfer' => -$amountCents,
            default => $amountCents,
        };

        $userId = auth()->id();
        $account = Account::where('user_id', $userId)->findOrFail($data['account_id']);
        $currency = strtoupper($data['currency'] ?? $account->home_currency);
        $exchangeRateCents = $this->resolveExchangeRate($account->home_currency, $currency, $data['date']);

        $tx = Transaction::create([
            'user_id' => $userId,
            'account_id' => $data['account_id'],
            'destination_account_id' => $data['destination_account_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'type' => $data['type'],
            'amount_cents' => $signed,
            'currency' => $currency,
            'exchange_rate_cents' => $exchangeRateCents,
            'date' => $data['date'],
            'description' => $data['description'],
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'paid',
            'is_pix' => $request->boolean('is_pix'),
            'pix_key' => $data['pix_key'] ?? null,
        ]);

        if (! empty($data['splits'])) {
            try {
                $this->splits->replaceSplits($tx, $data['splits']);
            } catch (InvalidTransactionSplitException $e) {
                $tx->delete();
                return back()->withErrors(['splits' => $e->getMessage()])->withInput();
            }
        }

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

        $account = Account::where('user_id', auth()->id())->findOrFail($data['account_id']);
        $currency = strtoupper($data['currency'] ?? $account->home_currency);
        $exchangeRateCents = $this->resolveExchangeRate($account->home_currency, $currency, $data['date']);

        $transaction->update([
            'account_id' => $data['account_id'],
            'destination_account_id' => $data['destination_account_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'type' => $data['type'],
            'amount_cents' => $signed,
            'currency' => $currency,
            'exchange_rate_cents' => $exchangeRateCents,
            'date' => $data['date'],
            'description' => $data['description'],
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'paid',
            'is_pix' => $request->boolean('is_pix'),
            'pix_key' => $data['pix_key'] ?? null,
        ]);

        if (array_key_exists('splits', $data)) {
            try {
                $this->splits->replaceSplits($transaction, $data['splits'] ?? []);
            } catch (InvalidTransactionSplitException $e) {
                return back()->withErrors(['splits' => $e->getMessage()])->withInput();
            }
        }

        return redirect()->route('transactions.index')->with('success', 'Transação atualizada.');
    }

    /**
     * Snapshot the FX rate at the transaction's date so historical
     * reports stay correct after the live rate moves. Returns
     * `null` (no snapshot) when the transaction is in the
     * account's home currency, or when the rate cannot be fetched.
     */
    private function resolveExchangeRate(string $homeCurrency, string $transactionCurrency, string $date): ?int
    {
        if ($homeCurrency === $transactionCurrency) {
            return null;
        }
        $rate = $this->fx->rate($transactionCurrency, $homeCurrency, $date);
        if ($rate === null) {
            return null;
        }
        return (int) round($rate * 100);
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
     * Show transaction details (splits, who paid, status per participant).
     */
    public function show(Transaction $transaction): Response
    {
        abort_unless($transaction->user_id === auth()->id(), 403);
        $transaction->load(['account', 'destinationAccount', 'category', 'splits.user', 'splits.category', 'splits.paidBy']);

        return Inertia::render('Transactions/Show', [
            'transaction' => $transaction,
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * Toggle a single split's "paid" status (AJAX).
     */
    public function toggleSplit(Transaction $transaction, TransactionSplit $split): JsonResponse|RedirectResponse
    {
        abort_unless($transaction->user_id === auth()->id(), 403);
        abort_unless($split->transaction_id === $transaction->id, 404);

        $this->splits->togglePaid($split);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'id' => $split->id,
                'is_paid' => $split->is_paid,
                'paid_at' => optional($split->paid_at)->toIso8601String(),
            ]);
        }
        return back();
    }

    /**
     * Shared props for create/edit views.
     */
    private function formProps(?Transaction $transaction = null): Response
    {
        $userId = auth()->id();
        $transaction?->load('splits.user');

        return Inertia::render('Transactions/Form', [
            'transaction' => $transaction,
            'accounts' => Account::where('user_id', $userId)->orderBy('name')->get(),
            'categories' => Category::where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }
}
