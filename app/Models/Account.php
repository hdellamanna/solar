<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent model representing a financial account (bank, cash, credit card, etc.).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $type
 * @property string $currency
 * @property string|null $color
 * @property string|null $icon
 * @property int $initial_balance_cents
 * @property bool $archived
 * @property-read int $balance_cents
 * @property-read string $balance
 */
class Account extends Model
{
    /** @use HasFactory<\Database\Factories\AccountFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'currency',
        'color',
        'icon',
        'initial_balance_cents',
        'archived',
    ];

    protected $casts = [
        'initial_balance_cents' => 'integer',
        'archived' => 'boolean',
    ];

    protected $appends = ['balance_cents', 'balance'];

    public const TYPES = [
        'checking'        => 'Conta corrente',
        'savings'         => 'Poupança',
        'credit_card'     => 'Cartão de crédito',
        'cash'            => 'Dinheiro',
        'investment'      => 'Investimento',
        'crypto'          => 'Criptomoedas',
        'multi_currency'  => 'Multi-moeda (Wise, Nomad, C6 Global, Inter Global)',
    ];

    /**
     * Account types that natively hold balances in more than one
     * currency (FASE 6A). Used by the UI to render the sub-balance
     * editor and by the dashboard to decide how to aggregate.
     */
    public const MULTI_CURRENCY_TYPES = ['multi_currency'];

    /**
     * The user that owns the account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All transactions that originated from this account.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Transfers that arrived in this account as destination.
     */
    public function destinationTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'destination_account_id');
    }

    /**
     * Sub-balances per currency for multi-currency accounts (FASE 6A).
     */
    public function balances(): HasMany
    {
        return $this->hasMany(AccountBalance::class);
    }

    /**
     * Computed current balance: initial_balance + signed sum of paid transactions.
     *
     * - For ordinary income/expense, only the source `account_id` matters; the
     *   stored `amount_cents` is already signed (income positive, expense negative).
     * - For a transfer, `amount_cents` is stored as negative (it represents the
     *   outflow from the source account). The destination account's balance
     *   accessor negates the sum of its incoming transfers, so the value flows
     *   from source to destination.
     */
    public function getBalanceCentsAttribute(): int
    {
        $initial = (int) $this->initial_balance_cents;
        $outflow = (int) $this->transactions()
            ->where('status', 'paid')
            ->sum('amount_cents');
        $inflow = -(int) $this->destinationTransactions()
            ->where('status', 'paid')
            ->sum('amount_cents');

        return $initial + $outflow + $inflow;
    }

    /**
     * True when this account can hold multiple currencies (FASE 6A).
     */
    public function getIsMultiCurrencyAttribute(): bool
    {
        return in_array($this->type, self::MULTI_CURRENCY_TYPES, true);
    }

    /**
     * The home currency of the account (defaults to BRL for
     * backwards compatibility). Sub-balances in other currencies
     * live in {@see AccountBalance} rows.
     */
    public function getHomeCurrencyAttribute(): string
    {
        return strtoupper($this->currency ?: 'BRL');
    }

    /**
     * Sub-balance for a specific currency. Returns the home balance
     * if no sub-balance row exists yet for the requested currency.
     */
    public function getBalanceForCurrency(string $currency): int
    {
        $currency = strtoupper($currency);
        if ($currency === $this->home_currency) {
            return $this->balance_cents;
        }
        $row = $this->balances()->where('currency', $currency)->first();
        return $row ? (int) $row->balance_cents : 0;
    }

    /**
     * Balance formatted as Brazilian Real string (R$ 1.234,56).
     */
    public function getBalanceAttribute(): string
    {
        $value = $this->balance_cents / 100;
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    /**
     * Scope: non-archived accounts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('archived', false);
    }

    /**
     * Scope: filter by account type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
