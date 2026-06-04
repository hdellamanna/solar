<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Recurrence rule used to generate transactions periodically.
 *
 * @property int $id
 * @property int $user_id
 * @property int $account_id
 * @property int|null $category_id
 * @property string $description
 * @property int $amount_cents
 * @property string $type
 * @property string $frequency
 * @property string $starts_at
 * @property string|null $ends_at
 * @property string|null $last_generated_at
 * @property bool $active
 */
class Recurrence extends Model
{
    /** @use HasFactory<\Database\Factories\RecurrenceFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'recurrences';

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'description',
        'amount_cents',
        'type',
        'frequency',
        'starts_at',
        'ends_at',
        'last_generated_at',
        'active',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'starts_at' => 'date:Y-m-d',
        'ends_at' => 'date:Y-m-d',
        'last_generated_at' => 'date:Y-m-d',
        'active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
