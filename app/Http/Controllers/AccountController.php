<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD operations for the authenticated user's accounts.
 */
class AccountController extends Controller
{
    /**
     * Display a listing of accounts with computed balances.
     */
    public function index(Request $request): Response
    {
        $accounts = Auth::user()->accounts()
            ->with('transactions')
            ->orderBy('archived')
            ->orderBy('name')
            ->get()
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type,
                'type_label' => Account::TYPES[$a->type] ?? $a->type,
                'currency' => $a->currency,
                'color' => $a->color,
                'icon' => $a->icon,
                'initial_balance_cents' => $a->initial_balance_cents,
                'archived' => $a->archived,
                'balance_cents' => $a->balance_cents,
                'balance' => $a->balance,
            ]);

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
        Auth::user()->accounts()->create($request->validated());

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
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'currency' => $account->currency,
                'color' => $account->color,
                'icon' => $account->icon,
                'initial_balance_cents' => $account->initial_balance_cents,
                'archived' => $account->archived,
            ],
            'types' => Account::TYPES,
        ]);
    }

    /**
     * Update the specified account in storage.
     */
    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorizeOwner($account);
        $account->update($request->validated());

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

    /**
     * Ensure the account belongs to the currently authenticated user.
     */
    protected function authorizeOwner(Account $account): void
    {
        abort_unless($account->user_id === Auth::id(), 403);
    }
}
