<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Financial transaction (income, expense or transfer).
 *
 * amount_cents is SIGNED: positive = inflow, negative = outflow.
 *
 * @property int $id
 * @property int $user_id
 * @property int $account_id
 * @property int|null $destination_account_id
 * @property int|null $category_id
 * @property int|null $recurrence_id
 * @property string $type
 * @property int $amount_cents
 * @property string $date
 * @property string $description
 * @property string|null $notes
 * @property string $status
 * @property bool $is_pix
 * @property string|null $pix_key
 * @property string|null $currency ISO-4217 3-letter (BRL, USD, EUR, GBP); nullable for legacy
 * @property int|null $exchange_rate_cents 1 unit of currency = N/100 of user's home currency, captured at creation time
 */
class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_id',
        'destination_account_id',
        'category_id',
        'recurrence_id',
        'type',
        'amount_cents',
        'currency',
        'exchange_rate_cents',
        'date',
        'description',
        'notes',
        'status',
        'is_pix',
        'pix_key',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'exchange_rate_cents' => 'integer',
        'date' => 'date:Y-m-d',
        'is_pix' => 'boolean',
    ];

    /**
     * Currency of the transaction. Defaults to the account's home
     * currency when null. FASE 6A.
     */
    public function getCurrencyAttribute(?string $value): string
    {
        if ($value) {
            return strtoupper($value);
        }
        return $this->account?->home_currency ?? 'BRL';
    }

    /**
     * Exchange rate captured at creation time, in cents (1 unit of
     * `currency` = `exchange_rate_cents/100` of the user's home
     * currency). Null when transaction is in the user's home
     * currency or for legacy rows pre-FASE 6A. FASE 6A.
     */
    public function getExchangeRateCentsAttribute(): ?int
    {
        return $this->attributes['exchange_rate_cents'] !== null
            ? (int) $this->attributes['exchange_rate_cents']
            : null;
    }

    protected $appends = ['amount_decimal', 'signed_amount', 'tag_list'];

    /**
     * Owner of the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Source account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Destination account (for transfers).
     */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    /**
     * Transaction category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Recurrence rule that generated this transaction (if any).
     */
    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(Recurrence::class);
    }

    /**
     * Tags attached to this transaction.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_transaction');
    }

    /**
     * Shares assigned to other users (splits).
     */
    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    /**
     * Accessor: amount expressed as decimal (e.g. 12345 -> 123.45).
     */
    public function getAmountDecimalAttribute(): float
    {
        return round($this->amount_cents / 100, 2);
    }

    /**
     * Accessor: alias for amount_cents. The column is already signed.
     */
    public function getSignedAmountAttribute(): int
    {
        return (int) $this->amount_cents;
    }

    /**
     * Accessor: comma-separated list of tag names for quick display.
     */
    public function getTagListAttribute(): string
    {
        if (! $this->relationLoaded('tags')) {
            return '';
        }
        return $this->tags->pluck('name')->implode(', ');
    }

    /**
     * Scope: filter by a date period (inclusive on both ends).
     */
    public function scopeInPeriod(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    /**
     * Scope: filter by account.
     */
    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope: filter by category.
     */
    public function scopeForCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope: only paid transactions.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope: only pending transactions.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
