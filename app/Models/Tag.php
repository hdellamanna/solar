<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * User-defined tag attached to transactions.
 * Slug is unique per user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $color
 * @property string|null $icon
 */
class Tag extends Model
{
    /** @use HasFactory<\Database\Factories\TagFactory> */
    use HasFactory;

    protected $table = 'tags';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'color',
        'icon',
    ];

    protected $appends = ['transaction_count', 'total_cents'];

    /**
     * Owner of the tag.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Transactions tagged with this tag.
     */
    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'tag_transaction');
    }

    /**
     * Scope: tags that are used by at least one transaction.
     */
    public function scopeUsed(Builder $query): Builder
    {
        return $query->whereHas('transactions');
    }

    /**
     * Accessor: count of transactions using this tag.
     */
    public function getTransactionCountAttribute(): int
    {
        if (array_key_exists('transactions_count', $this->attributes)) {
            return (int) $this->attributes['transactions_count'];
        }
        return $this->transactions()->count();
    }

    /**
     * Accessor: sum of transaction amounts in cents for this tag.
     */
    public function getTotalCentsAttribute(): int
    {
        return (int) $this->transactions()->sum('amount_cents');
    }

    /** Scope used by SearchController: restrict to a given user. */
    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
