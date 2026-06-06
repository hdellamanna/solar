<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Spending budget for a category over a recurring period.
 *
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property string $name
 * @property int $amount_cents
 * @property string $period
 * @property string $starts_at
 * @property string|null $ends_at
 * @property int $alert_threshold
 * @property string|null $color
 * @property string|null $icon
 */
class Budget extends Model
{
    /** @use HasFactory<\Database\Factories\BudgetFactory> */
    use HasFactory, SoftDeletes;

    /** Available period keys (snake_case label -> label pt-BR). */
    public const PERIODS = [
        'weekly' => 'Semanal',
        'monthly' => 'Mensal',
        'quarterly' => 'Trimestral',
        'yearly' => 'Anual',
    ];

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'amount_cents',
        'period',
        'starts_at',
        'ends_at',
        'alert_threshold',
        'color',
        'icon',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'alert_threshold' => 'integer',
        'starts_at' => 'date:Y-m-d',
        'ends_at' => 'date:Y-m-d',
    ];

    protected $appends = [
        'amount_decimal',
        'spent_cents',
        'remaining_cents',
        'progress_percent',
        'status',
        'days_remaining',
        'period_label',
    ];

    /**
     * Owner of the budget.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Category the budget is targeting.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Compute the [start, end] window (inclusive) of the current period
     * based on `starts_at` and `period`. If the original window is in the
     * past, it rolls forward to the most recent period that contains today.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function currentPeriod(): array
    {
        $origin = CarbonImmutable::parse($this->starts_at)->startOfDay();
        $today = CarbonImmutable::today();
        $unit = $this->periodUnit();
        $count = $this->periodCount();

        // If a fixed ends_at is set, just return the [starts_at, ends_at] window
        // and roll forward in fixed-size chunks until the window contains today.
        if ($this->ends_at) {
            $endOrigin = CarbonImmutable::parse($this->ends_at)->endOfDay();
            $span = (int) $origin->diffInDays($endOrigin, false);
            $start = $origin;
            $end = $endOrigin;
            // Roll forward (preserving span) until end >= today.
            while ($end->lessThan($today)) {
                $start = $start->add($unit, $count);
                $end = $start->copy()->addDays($span)->endOfDay();
            }
            return [$start->startOfDay(), $end->endOfDay()];
        }

        // Open-ended: roll forward until the [start, start+period) window contains today.
        $start = $origin;
        while ($start->add($unit, $count)->lessThanOrEqualTo($today)) {
            $start = $start->add($unit, $count);
        }
        $end = $start->copy()->add($unit, $count)->subDay()->endOfDay();
        return [$start->startOfDay(), $end->endOfDay()];
    }

    /**
     * Unit string used by Carbon to advance the period.
     */
    protected function periodUnit(): string
    {
        return match ($this->period) {
            'weekly' => 'weeks',
            'monthly' => 'months',
            'quarterly' => 'months', // 3 months
            'yearly' => 'years',
            default => 'months',
        };
    }

    /**
     * Count to advance for the configured period.
     */
    protected function periodCount(): int
    {
        return match ($this->period) {
            'weekly' => 1,
            'monthly' => 1,
            'quarterly' => 3,
            'yearly' => 1,
            default => 1,
        };
    }

    /**
     * Sum of expense transactions in this category, within the current
     * period, owned by the budget owner. Returns a positive integer in
     * cents (absolute value of the outflow).
     */
    public function getSpentCentsAttribute(): int
    {
        if (! array_key_exists('spent_cents', $this->attributes)) {
            [$start, $end] = $this->currentPeriod();
            $sum = Transaction::query()
                ->where('user_id', $this->user_id)
                ->where('category_id', $this->category_id)
                ->where('type', 'expense')
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->sum('amount_cents');
            $this->attributes['spent_cents'] = (int) abs($sum);
        }
        return (int) $this->attributes['spent_cents'];
    }

    /**
     * Remaining budget in cents (clamped to >= 0 if exceeded).
     */
    public function getRemainingCentsAttribute(): int
    {
        $spent = $this->spent_cents;
        $remaining = $this->amount_cents - $spent;
        return (int) $remaining; // can be negative
    }

    /**
     * Progress as a percentage in [0, 100].
     */
    public function getProgressPercentAttribute(): float
    {
        if ($this->amount_cents <= 0) {
            return 0.0;
        }
        $pct = ($this->spent_cents / $this->amount_cents) * 100.0;
        return (float) max(0.0, min(100.0, $pct));
    }

    /**
     * Status bucket: safe, warning or exceeded.
     */
    public function getStatusAttribute(): string
    {
        $pct = ($this->amount_cents > 0)
            ? ($this->spent_cents / $this->amount_cents) * 100.0
            : 0.0;
        if ($pct >= 100.0) {
            return 'exceeded';
        }
        if ($pct >= (int) $this->alert_threshold) {
            return 'warning';
        }
        return 'safe';
    }

    /**
     * Number of days remaining until the end of the current period
     * (or 0 if the period already ended).
     */
    public function getDaysRemainingAttribute(): int
    {
        [, $end] = $this->currentPeriod();
        $today = CarbonImmutable::today();
        $diff = $today->diffInDays($end, false);
        return (int) max(0, $diff);
    }

    /**
     * Accessor: amount as decimal.
     */
    public function getAmountDecimalAttribute(): float
    {
        return round($this->amount_cents / 100, 2);
    }

    /**
     * Accessor: human-readable period label.
     */
    public function getPeriodLabelAttribute(): string
    {
        return self::PERIODS[$this->period] ?? $this->period;
    }

    /**
     * Scope: budgets whose current period window is open (today is between
     * starts_at and the rolled end date).
     */
    public function scopeActive(Builder $query): Builder
    {
        $today = CarbonImmutable::today()->toDateString();
        return $query->where('starts_at', '<=', $today)
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today);
            });
    }

    /**
     * Scope: filter by period key.
     */
    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    /**
     * Scope: budgets whose current period ends within N days.
     */
    public function scopeEndingSoon(Builder $query, int $days = 7): Builder
    {
        $today = CarbonImmutable::today();
        $limit = $today->addDays($days)->toDateString();
        return $query->where(function (Builder $q) use ($today, $limit) {
            $q->whereNull('ends_at')->orWhereBetween('ends_at', [$today->toDateString(), $limit]);
        });
    }
}
