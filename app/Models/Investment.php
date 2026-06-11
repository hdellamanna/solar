<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent model representing a single investment position (FASE 5).
 *
 * An investment is a tracked asset the user holds in their portfolio —
 * a stock, a fund share, a crypto coin, a fixed-income bond, or a
 * treasury bond. Prices are stored in **cents** (bigint) to avoid
 * floating-point drift; quantity uses 8 decimal places to support
 * fractional crypto (e.g. 0.05 BTC).
 *
 * P&L is computed from `quantity * average_price_cents` (cost basis)
 * and `quantity * current_price_cents` (current market value). When
 * `current_price_cents` is null, the current value falls back to 0 and
 * P&L is reported as 0 — the field is intentionally nullable to leave
 * the door open for live quote services in FASE 9.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $type
 * @property string|null $ticker
 * @property float $quantity
 * @property int $average_price_cents
 * @property int|null $current_price_cents
 * @property string $currency
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $acquired_at
 */
class Investment extends Model
{
    /** @use HasFactory<\Database\Factories\InvestmentFactory> */
    use HasFactory, SoftDeletes;

    /** Type: Brazilian stocks (B3) — PETR4, VALE3, ITSA4, etc. */
    public const TYPE_STOCK = 'stock';

    /** Type: investment funds (FIIs, multimercado, etc). */
    public const TYPE_FUND = 'fund';

    /** Type: cryptocurrencies (BTC, ETH, etc). */
    public const TYPE_CRYPTO = 'crypto';

    /** Type: fixed-income assets (CDB, LCI, LCA, debêntures, etc). */
    public const TYPE_FIXED_INCOME = 'fixed_income';

    /** Type: treasury bonds (Tesouro Direto — Selic, IPCA+, Prefixado). */
    public const TYPE_TREASURY = 'treasury';

    /**
     * Human-readable label map for the UI. Order is the canonical
     * ordering shown in the type picker.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        self::TYPE_STOCK        => 'Ação',
        self::TYPE_FUND         => 'Fundo (FII / multimercado)',
        self::TYPE_CRYPTO       => 'Criptomoeda',
        self::TYPE_FIXED_INCOME => 'Renda fixa (CDB / LCI / LCA)',
        self::TYPE_TREASURY     => 'Tesouro Direto',
    ];

    /**
     * Color palette paired with the type. Apple-style cards pick the
     * matching accent for the badge, hero numbers, and P&L line.
     *
     * @var array<string, string>
     */
    public const TYPE_COLORS = [
        self::TYPE_STOCK        => '#3b82f6', // blue
        self::TYPE_FUND         => '#6366f1', // indigo
        self::TYPE_CRYPTO       => '#f59e0b', // amber
        self::TYPE_FIXED_INCOME => '#10b981', // emerald
        self::TYPE_TREASURY     => '#8b5cf6', // violet
    ];

    /**
     * ISO-4217 currency symbol table. Defaults to BRL since the
     * product is Brazilian; the table is here so USD/EUR/GBP are
     * supported without touching the model again.
     *
     * @var array<string, string>
     */
    public const CURRENCY_SYMBOLS = [
        'BRL' => 'R$',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'ticker',
        'quantity',
        'average_price_cents',
        'current_price_cents',
        'currency',
        'notes',
        'acquired_at',
    ];

    protected $casts = [
        'quantity'             => 'float',
        'average_price_cents'  => 'integer',
        'current_price_cents'  => 'integer',
        'acquired_at'          => 'date',
    ];

    // --- Relations ----------------------------------------------------------

    /**
     * The user that owns this investment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Scopes -------------------------------------------------------------

    /**
     * Restrict the query to a single investment type.
     *
     * @param  Builder<Investment> $q
     * @return Builder<Investment>
     */
    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    /**
     * Restrict the query to a single user.
     *
     * @param  Builder<Investment> $q
     * @return Builder<Investment>
     */
    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    // --- Accessors ----------------------------------------------------------

    /**
     * Total cost basis in cents: quantity * average_price, rounded.
     * Always a non-negative integer.
     */
    public function getTotalInvestedCentsAttribute(): int
    {
        return (int) round($this->quantity * (int) $this->average_price_cents);
    }

    /**
     * Current market value in cents: quantity * current_price.
     * When `current_price_cents` is null, falls back to 0 so that
     * P&L is reported as a full loss — the user simply hasn't told
     * us the current value yet.
     */
    public function getCurrentValueCentsAttribute(): int
    {
        if ($this->current_price_cents === null) {
            return 0;
        }
        return (int) round($this->quantity * (int) $this->current_price_cents);
    }

    /**
     * Unrealized profit / loss in cents: current - cost basis.
     * Negative when the asset is underwater.
     */
    public function getProfitLossCentsAttribute(): int
    {
        return (int) ($this->current_value_cents - $this->total_invested_cents);
    }

    /**
     * P&L as a percentage of the cost basis. Null when there's no
     * cost basis (quantity = 0 or average_price_cents = 0) — the
     * UI uses this to skip the percent line gracefully.
     */
    public function getProfitLossPercentAttribute(): ?float
    {
        $basis = $this->total_invested_cents;
        if ($basis <= 0) {
            return null;
        }
        return round(($this->profit_loss_cents / $basis) * 100, 2);
    }

    /**
     * True when the current price has been set (so P&L is meaningful).
     */
    public function getHasCurrentPriceAttribute(): bool
    {
        return $this->current_price_cents !== null;
    }

    /**
     * True when P&L is positive (a gain). False when zero or negative.
     */
    public function getIsProfitAttribute(): bool
    {
        return $this->profit_loss_cents > 0;
    }

    /**
     * ISO-4217 currency symbol, e.g. R$ for BRL, $ for USD.
     */
    public function getCurrencySymbolAttribute(): string
    {
        return self::CURRENCY_SYMBOLS[strtoupper($this->currency ?? 'BRL')] ?? ($this->currency ?? 'BRL') . ' ';
    }

    /**
     * Quantity as a human-friendly decimal with no trailing zeros,
     * e.g. 0.5 instead of 0.50000000.
     */
    public function getFormattedQuantityAttribute(): string
    {
        $q = (float) $this->quantity;
        // Use up to 8 decimals (matches DB precision), trim trailing zeros.
        $formatted = rtrim(rtrim(sprintf('%.8F', $q), '0'), '.');
        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }

    /**
     * Average price in the currency's decimal form (e.g. 10.50 for R$ 10,50).
     * Useful for pre-filling form fields.
     */
    public function getAveragePriceDecimalAttribute(): float
    {
        return round($this->average_price_cents / 100, 8);
    }

    /**
     * Current price in decimal form. Null when unset.
     */
    public function getCurrentPriceDecimalAttribute(): ?float
    {
        if ($this->current_price_cents === null) {
            return null;
        }
        return round($this->current_price_cents / 100, 8);
    }

    /**
     * Human-readable type label (uses the {@see TYPES} map).
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Color hex for the type, used in the badge / hero number.
     */
    public function getTypeColorAttribute(): string
    {
        return self::TYPE_COLORS[$this->type] ?? '#64748b';
    }
}
