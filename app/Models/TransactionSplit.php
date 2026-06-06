<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A share/portion of a transaction assigned to a specific user.
 *
 * amount_cents is SIGNED: it mirrors the parent transaction's convention,
 * so expense splits are stored as negative, income splits as positive.
 *
 * @property int $id
 * @property int $transaction_id
 * @property int $user_id
 * @property int|null $category_id
 * @property int|null $paid_by_user_id
 * @property string|null $description
 * @property int $amount_cents
 * @property bool $is_paid
 * @property \Illuminate\Support\Carbon|null $paid_at
 */
class TransactionSplit extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionSplitFactory> */
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'category_id',
        'paid_by_user_id',
        'description',
        'amount_cents',
        'is_paid',
        'paid_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
    ];

    protected $appends = ['amount_decimal'];

    /**
     * Parent transaction.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * User who owes / receives this share.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Optional category override for this share.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * User who actually paid for this share (defaults to transaction owner).
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    /**
     * Accessor: amount expressed as decimal (e.g. 12345 -> 123.45).
     */
    public function getAmountDecimalAttribute(): float
    {
        return round($this->amount_cents / 100, 2);
    }
}
