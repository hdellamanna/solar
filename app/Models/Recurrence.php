<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon|null $last_generated_at
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
        'starts_at' => 'date',
        'ends_at' => 'date',
        'last_generated_at' => 'date',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @param \Illuminate\Database\Eloquent\Builder<Recurrence> $q */
    public function scopeActive($q)
    {
        return $q->where('active', true);
    }

    /** @param \Illuminate\Database\Eloquent\Builder<Recurrence> $q */
    public function scopeDue($q, ?\DateTimeInterface $asOf = null)
    {
        $asOf ??= now();
        return $q->where(function ($w) use ($asOf) {
            $w->whereNull('last_generated_at')
              ->whereDate('starts_at', '<=', $asOf)
              ->orWhereDate('last_generated_at', '<', $asOf);
        })->where(function ($w) use ($asOf) {
            $w->whereNull('ends_at')->orWhereDate('ends_at', '>=', $asOf);
        });
    }

    /** Next date the recurrence should generate a transaction. */
    public function getNextRunAtAttribute(): \Illuminate\Support\Carbon
    {
        $base = $this->last_generated_at ?? $this->starts_at;
        $base = \Illuminate\Support\Carbon::parse($base);

        return match ($this->frequency) {
            'daily' => $base->copy()->addDay(),
            'weekly' => $base->copy()->addWeek(),
            'monthly' => $base->copy()->addMonth(),
            'yearly' => $base->copy()->addYear(),
            default => $base->copy()->addMonth(),
        };
    }

    /** Human-friendly frequency label in pt-BR. */
    public function getHumanFrequencyAttribute(): string
    {
        return match ($this->frequency) {
            'daily' => 'Diária',
            'weekly' => 'Semanal',
            'monthly' => 'Mensal',
            'yearly' => 'Anual',
            default => ucfirst((string) $this->frequency),
        };
    }

    public function getAmountDecimalAttribute(): float
    {
        return $this->amount_cents / 100;
    }
}
