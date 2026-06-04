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
        'checking' => 'Conta corrente',
        'savings' => 'Poupança',
        'credit_card' => 'Cartão de crédito',
        'cash' => 'Dinheiro',
        'investment' => 'Investimento',
        'crypto' => 'Criptomoedas',
    ];

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
     * Computed current balance: initial_balance + sum of paid transactions.
     *
     * Income is added (positive), expense is subtracted (negative amounts),
     * and transfers count as outflow from this account and inflow to the destination.
     */
    public function getBalanceCentsAttribute(): int
    {
        $initial = (int) $this->initial_balance_cents;
        $sum = (int) $this->transactions()
            ->where('status', 'paid')
            ->sum('amount_cents');

        return $initial + $sum;
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
