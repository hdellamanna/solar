<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eloquent model representing a financial savings goal (FASE 4A).
 *
 * The user creates a goal with a `target_amount_cents` and an optional
 * `deadline`. They deposit money over time via the `contribute` action,
 * which increments `current_amount_cents`. When current >= target,
 * `achieved_at` is stamped and the goal is considered completed.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $target_amount_cents
 * @property int $current_amount_cents
 * @property \Illuminate\Support\Carbon|null $deadline
 * @property string $icon
 * @property string $color
 * @property \Illuminate\Support\Carbon|null $achieved_at
 * @property \Illuminate\Support\Carbon|null $archived_at
 */
class Goal extends Model
{
    /** @use HasFactory<\Database\Factories\GoalFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'target_amount_cents',
        'current_amount_cents',
        'deadline',
        'icon',
        'color',
    ];

    protected $casts = [
        'target_amount_cents' => 'integer',
        'current_amount_cents' => 'integer',
        'deadline' => 'date',
        'achieved_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Scopes -------------------------------------------------------------

    /** @param Builder<Goal> $q */
    public function scopeActive($q): Builder
    {
        return $q->whereNull('archived_at');
    }

    /** @param Builder<Goal> $q */
    public function scopeAchieved($q): Builder
    {
        return $q->whereNotNull('achieved_at');
    }

    /** @param Builder<Goal> $q */
    public function scopeInProgress($q): Builder
    {
        return $q->whereNull('achieved_at');
    }

    // --- Accessors ----------------------------------------------------------

    /** Progress as a 0-100 float, capped at 100. */
    public function getProgressPercentAttribute(): float
    {
        if ($this->target_amount_cents <= 0) {
            return 0.0;
        }
        return round(min(100, ($this->current_amount_cents / $this->target_amount_cents) * 100), 1);
    }

    /** Amount still needed to reach the target. Always >= 0. */
    public function getRemainingCentsAttribute(): int
    {
        return max(0, (int) $this->target_amount_cents - (int) $this->current_amount_cents);
    }

    /** True when the goal has been completed (current >= target). */
    public function getIsAchievedAttribute(): bool
    {
        return $this->achieved_at !== null
            || (int) $this->current_amount_cents >= (int) $this->target_amount_cents;
    }

    /** Days remaining until deadline. Negative if past. Null if no deadline. */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->deadline) {
            return null;
        }
        $today = Carbon::today();
        $dl = $this->deadline instanceof Carbon ? $this->deadline : Carbon::parse($this->deadline);
        return (int) $today->diffInDays($dl, false);
    }

    /** Target formatted as R$ 1.234,56 (pt-BR). */
    public function getTargetFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->target_amount_cents / 100, 2, ',', '.');
    }

    /** Current formatted as R$ 1.234,56. */
    public function getCurrentFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->current_amount_cents / 100, 2, ',', '.');
    }

    /** Target as a float (decimal reais), useful for input fields. */
    public function getTargetDecimalAttribute(): float
    {
        return round($this->target_amount_cents / 100, 2);
    }

    /** Current as a float. */
    public function getCurrentDecimalAttribute(): float
    {
        return round($this->current_amount_cents / 100, 2);
    }

    // --- Mutators -----------------------------------------------------------

    /**
     * Add (in cents, positive int) to the running current amount.
     * Auto-stamps achieved_at when crossing the target.
     * Returns the new current_amount_cents.
     */
    public function contribute(int $cents): int
    {
        $cents = max(0, $cents);
        $this->current_amount_cents = (int) $this->current_amount_cents + $cents;
        $this->markAchievedIfReady();
        $this->save();
        return (int) $this->current_amount_cents;
    }

    /**
     * Subtract (in cents, positive int) from the running current amount.
     * Floors at 0. Does not unmark an achieved goal.
     */
    public function withdraw(int $cents): int
    {
        $cents = max(0, $cents);
        $this->current_amount_cents = max(0, (int) $this->current_amount_cents - $cents);
        $this->save();
        return (int) $this->current_amount_cents;
    }

    /**
     * Stamp achieved_at (if not already) once current >= target.
     * Idempotent.
     */
    public function markAchievedIfReady(): void
    {
        if ($this->achieved_at === null
            && (int) $this->current_amount_cents >= (int) $this->target_amount_cents
            && (int) $this->target_amount_cents > 0) {
            $this->achieved_at = now();
        }
    }

    /**
     * Soft-archive the goal (it's hidden from the default list).
     */
    public function archive(): void
    {
        $this->archived_at = now();
        $this->save();
    }
}
