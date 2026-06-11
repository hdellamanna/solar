<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Eloquent model representing a tracked debt / financing contract (FASE 5).
 *
 * The user logs a debt once with a starting balance, an annual
 * interest rate, a monthly payment and a chosen amortization
 * strategy (SAC or Price). The model is purely a tracker — the
 * month-by-month math that simulates how the balance evolves
 * lives in {@see \App\Services\AmortizationService}.
 *
 * Money convention: `*_cents` fields are integers in the smallest
 * currency unit (e.g. R$ 1,00 = 100). The annual rate is stored
 * as a decimal (0.1250 = 12.50% a.a.). `is_paid_off` flips true
 * when the user marks the debt as fully settled (a separate action
 * from the auto-detection; the user-driven action is what stamps
 * `paid_off_at`).
 *
 * @property int $id
 * @property int $user_id
 * @property string $creditor
 * @property string|null $description
 * @property int $total_balance_cents
 * @property float $interest_rate_annual
 * @property int $monthly_payment_cents
 * @property \Illuminate\Support\Carbon $start_date
 * @property string $payoff_strategy
 * @property string $currency
 * @property string|null $notes
 * @property bool $is_paid_off
 * @property \Illuminate\Support\Carbon|null $paid_off_at
 */
class Debt extends Model
{
    /** @use HasFactory<\Database\Factories\DebtFactory> */
    use HasFactory;
    use SoftDeletes;

    public const STRATEGY_SAC = 'sac';
    public const STRATEGY_PRICE = 'price';

    protected $fillable = [
        'user_id',
        'creditor',
        'description',
        'total_balance_cents',
        'interest_rate_annual',
        'monthly_payment_cents',
        'start_date',
        'payoff_strategy',
        'currency',
        'notes',
        'is_paid_off',
        'paid_off_at',
    ];

    protected $casts = [
        'total_balance_cents' => 'integer',
        'monthly_payment_cents' => 'integer',
        'interest_rate_annual' => 'float',
        'start_date' => 'date',
        'is_paid_off' => 'boolean',
        'paid_off_at' => 'datetime',
    ];

    // --- Relations ----------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Scopes -------------------------------------------------------------

    /**
     * Active (i.e. not yet paid off) debts.
     *
     * @param Builder<Debt> $q
     */
    public function scopeActive($q): Builder
    {
        return $q->where('is_paid_off', false);
    }

    /**
     * Debts the user has marked as fully settled.
     *
     * @param Builder<Debt> $q
     */
    public function scopePaidOff($q): Builder
    {
        return $q->where('is_paid_off', true);
    }

    /**
     * Restrict a query to the given user id.
     *
     * @param Builder<Debt> $q
     */
    public function scopeForUser($q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    // --- Accessors ----------------------------------------------------------

    /**
     * Monthly interest rate (annual / 12), as a decimal (e.g. 0.01
     * for 1% a.m. when the annual is 0.12). Returns 0.0 when the
     * rate is zero so downstream math stays integer-safe.
     */
    public function getMonthlyInterestRateAttribute(): float
    {
        return round(((float) $this->interest_rate_annual) / 12, 8);
    }

    /**
     * Currency symbol derived from the ISO-4217 code (BRL → R$,
     * USD → $, EUR → €). Used by the front-end to render amounts
     * without a round-trip.
     */
    public function getCurrencySymbolAttribute(): string
    {
        return match (strtoupper($this->currency ?? 'BRL')) {
            'BRL' => 'R$',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            default => $this->currency . ' ',
        };
    }

    /**
     * Rough payoff estimate (months) using a simple linear projection:
     * `balance / payment` rounded up, with a minimum of 1 month.
     * For a precise month-by-month schedule (including interest),
     * use {@see \App\Services\AmortizationService}.
     */
    public function getEstimatedPayoffMonthsAttribute(): int
    {
        $payment = (int) $this->monthly_payment_cents;
        $balance = (int) $this->total_balance_cents;
        if ($payment <= 0 || $balance <= 0) {
            return 0;
        }
        return (int) ceil($balance / $payment);
    }

    /**
     * Balance as a decimal reais (R$ 1.234,56) — useful for input
     * fields and back-end forms that take floating-point values.
     */
    public function getTotalBalanceDecimalAttribute(): float
    {
        return round($this->total_balance_cents / 100, 2);
    }

    /**
     * Monthly payment as a decimal reais.
     */
    public function getMonthlyPaymentDecimalAttribute(): float
    {
        return round($this->monthly_payment_cents / 100, 2);
    }

    /**
     * Annual interest rate as a percentage (12.5 for 12.50% a.a.).
     * Useful for the form helper text and the badge label.
     */
    public function getInterestRatePercentAttribute(): float
    {
        return round(((float) $this->interest_rate_annual) * 100, 4);
    }

    /**
     * Balance formatted in pt-BR (R$ 1.234,56).
     */
    public function getTotalBalanceFormattedAttribute(): string
    {
        return $this->currency_symbol . ' ' . number_format($this->total_balance_cents / 100, 2, ',', '.');
    }

    /**
     * Monthly payment formatted in pt-BR.
     */
    public function getMonthlyPaymentFormattedAttribute(): string
    {
        return $this->currency_symbol . ' ' . number_format($this->monthly_payment_cents / 100, 2, ',', '.');
    }

    /**
     * True when the user has marked this debt as paid off.
     * Mirrors `is_paid_off` but always returns a non-null bool.
     */
    public function getIsSettledAttribute(): bool
    {
        return (bool) $this->is_paid_off;
    }

    /**
     * Stamp the debt as paid off (idempotent). Sets `paid_off_at`
     * to the current time only on the first call. Used by the
     * `markAsPaidOff` controller action — the user-driven way to
     * settle a debt regardless of what the simulator says.
     */
    public function markAsPaidOff(): void
    {
        if ($this->is_paid_off) {
            return;
        }
        $this->is_paid_off = true;
        $this->paid_off_at = $this->paid_off_at ?? Carbon::now();
        $this->save();
    }
}
