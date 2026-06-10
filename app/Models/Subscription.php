<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eloquent model for a tracked subscription (FASE 4B).
 *
 * A subscription is a recurring charge the user is paying for outside
 * of a specific merchant relationship in Solar (i.e. they are not
 * importing a statement, they just want to keep an eye on "what
 * am I paying for every month"). It can be linked to a Recurrence
 * (when a Solar rule already auto-generates the transaction) or
 * kept standalone.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $amount_cents
 * @property string $currency
 * @property int $billing_day
 * @property int|null $account_id
 * @property int|null $category_id
 * @property int|null $recurrence_id
 * @property string $icon
 * @property string $color
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $notes
 */
class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'amount_cents',
        'currency',
        'billing_day',
        'account_id',
        'category_id',
        'recurrence_id',
        'icon',
        'color',
        'active',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'billing_day' => 'integer',
        'active' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    // --- Relations ----------------------------------------------------------

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

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(Recurrence::class);
    }

    // --- Scopes -------------------------------------------------------------

    /** @param Builder<Subscription> $q */
    public function scopeActive($q): Builder
    {
        return $q->where('active', true)->whereNull('cancelled_at');
    }

    /** @param Builder<Subscription> $q */
    public function scopeNotCancelled($q): Builder
    {
        return $q->whereNull('cancelled_at');
    }

    /** @param Builder<Subscription> $q */
    public function scopeUpcoming($q, int $limit = 3): Builder
    {
        return $q->active()->orderBy('next_billing_at_raw');
    }

    // --- Accessors ----------------------------------------------------------

    /** Amount as decimal reais, useful for input fields. */
    public function getAmountDecimalAttribute(): float
    {
        return round($this->amount_cents / 100, 2);
    }

    /** Amount formatted as R$ 1.234,56 (pt-BR). */
    public function getAmountFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->amount_cents / 100, 2, ',', '.');
    }

    /**
     * Next billing date as a Carbon instance, derived from `billing_day`.
     *
     * - If today is before the billing day of this month, returns this month's date.
     * - Otherwise, returns next month's date (capped at the month's last day so
     *   billing_day=31 in February resolves to Feb 28 / 29).
     */
    public function getNextBillingAtAttribute(): Carbon
    {
        $today = Carbon::today();
        $day = max(1, min(31, (int) $this->billing_day));

        $thisMonth = $today->copy()->day(min($day, $today->daysInMonth));
        if ($thisMonth->lessThan($today)) {
            // Already past — roll to next month
            $nextMonth = $today->copy()->addMonthNoOverflow();
            return $nextMonth->day(min($day, $nextMonth->daysInMonth));
        }
        return $thisMonth;
    }

    /** Days until the next billing date (negative if past — should not happen with the accessor). */
    public function getDaysUntilBillingAttribute(): int
    {
        return (int) Carbon::today()->diffInDays($this->next_billing_at, false);
    }

    /** Monthly equivalent in cents (used for total aggregations). */
    public function getMonthlyCentsAttribute(): int
    {
        if ($this->cancelled_at) {
            return 0;
        }
        return (int) $this->amount_cents;
    }

    /** Yearly equivalent in cents. */
    public function getYearlyCentsAttribute(): int
    {
        return $this->monthly_cents * 12;
    }

    /** True when the subscription has been soft-cancelled. */
    public function getIsCancelledAttribute(): bool
    {
        return $this->cancelled_at !== null;
    }
}
