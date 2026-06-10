<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Services\FxRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD operations for the authenticated user's accounts.
 *
 * FASE 6A — multi-currency: when the account type is `multi_currency`
 * the request may include a `balances` array of {currency, balance_cents}
 * pairs that are persisted as {@see AccountBalance} sub-rows.
 */
class AccountController extends Controller
{
    public function __construct(private readonly FxRateService $fx)
    {
    }

    /**
     * Display a listing of accounts with computed balances.
     */
    public function index(Request $request): Response
    {
        $accounts = Auth::user()->accounts()
            ->with(['transactions', 'balances'])
            ->orderBy('archived')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $a) => $this->serialize($a));

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'types' => Account::TYPES,
        ]);
    }

    /**
     * Show the form for creating a new account.
     */
    public function create(): Response
    {
        return Inertia::render('Accounts/Create', [
            'types' => Account::TYPES,
        ]);
    }

    /**
     * Persist a new account for the current user.
     */
    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $balances = $data['balances'] ?? null;
        unset($data['balances']);

        $account = DB::transaction(function () use ($data, $balances) {
            $account = Auth::user()->accounts()->create($data);
            if (is_array($balances)) {
                foreach ($balances as $b) {
                    $account->balances()->create([
                        'currency' => strtoupper($b['currency']),
                        'balance_cents' => (int) $b['balance_cents'],
                    ]);
                }
            }
            return $account;
        });

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Conta criada com sucesso.');
    }

    /**
     * Show the form for editing the specified account.
     */
    public function edit(Account $account): Response
    {
        $this->authorizeOwner($account);

        return Inertia::render('Accounts/Edit', [
            'account' => $this->serialize($account),
            'types' => Account::TYPES,
        ]);
    }

    /**
     * Update the specified account in storage.
     */
    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorizeOwner($account);
        $data = $request->validated();
        $balances = $data['balances'] ?? null;
        unset($data['balances']);

        DB::transaction(function () use ($account, $data, $balances) {
            $account->update($data);
            if (is_array($balances)) {
                // Replace strategy: simpler than diffing, accounts have
                // at most a handful of currency rows.
                $account->balances()->delete();
                foreach ($balances as $b) {
                    $account->balances()->create([
                        'currency' => strtoupper($b['currency']),
                        'balance_cents' => (int) $b['balance_cents'],
                    ]);
                }
            }
        });

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Conta atualizada.');
    }

    /**
     * Soft-delete the account. Block deletion if it still has transactions.
     */
    public function destroy(Account $account): RedirectResponse
    {
        $this->authorizeOwner($account);

        if ($account->transactions()->withTrashed()->exists()) {
            return back()->with(
                'error',
                'Esta conta possui transações. Arquive-a em vez de excluí-la.',
            );
        }

        $account->delete();
        return redirect()
            ->route('accounts.index')
            ->with('success', 'Conta removida.');
    }

    protected function authorizeOwner(Account $account): void
    {
        abort_unless($account->user_id === Auth::id(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Account $a): array
    {
        $balances = $a->balances->map(fn (AccountBalance $b) => [
            'currency' => $b->currency,
            'balance_cents' => (int) $b->balance_cents,
        ])->values()->all();

        return [
            'id' => $a->id,
            'name' => $a->name,
            'type' => $a->type,
            'type_label' => Account::TYPES[$a->type] ?? $a->type,
            'currency' => $a->currency,
            'home_currency' => $a->home_currency,
            'is_multi_currency' => $a->is_multi_currency,
            'color' => $a->color,
            'icon' => $a->icon,
            'initial_balance_cents' => $a->initial_balance_cents,
            'archived' => $a->archived,
            'balance_cents' => $a->balance_cents,
            'balance' => $a->balance,
            'balances' => $balances,
        ];
    }
}
