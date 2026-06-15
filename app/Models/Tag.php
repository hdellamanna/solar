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
 * FASE 7 — i18n: the tag carries a `name` column (kept for
 * pre-FASE-7 query compatibility — see the
 * `add_localized_names_to_tags_table` migration for the rationale)
 * plus three localized variants (`name_pt`, `name_es`, `name_en`).
 * The `getNameAttribute()` accessor returns the active locale's
 * value with the same fallback chain as {@see Category::getNameAttribute()}.
 *
 * Slug is derived from the stable `name_pt` value (not the localized
 * accessor) so it stays consistent across locale changes — the
 * TagController's slug generator reads `$tag->name_pt` explicitly.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name         (legacy column; kept in sync with name_pt)
 * @property string|null $name_pt
 * @property string|null $name_es
 * @property string|null $name_en
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
        'name_pt',
        'name_es',
        'name_en',
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

    /**
     * FASE 7 — i18n accessor: same shape as
     * {@see Category::getNameAttribute()} — falls back through the
     * 3 localized columns, then the legacy `name` column, then
     * `#<id>`.
     */
    public function getNameAttribute(): string
    {
        $locale = (string) app()->getLocale();
        $short = strtolower(explode('-', $locale)[0] ?? 'pt');
        $key = "name_{$short}";

        $rawName = $this->getAttributes()['name'] ?? null;
        $candidate = $this->getAttributes()[$key]
            ?? $this->getAttributes()['name_pt']
            ?? $this->getAttributes()['name_es']
            ?? $this->getAttributes()['name_en']
            ?? (is_string($rawName) ? $rawName : null);

        if (is_string($candidate) && $candidate !== '') {
            return $candidate;
        }

        return "#{$this->id}";
    }

    /**
     * FASE 7 — i18n boot hook. Mirrors Category's: keeps the legacy
     * `name` column in sync with `name_pt` for pre-FASE-7 query
     * patterns.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Tag $tag): void {
            $tag->syncLegacyNameFromLocalized();
        });

        static::updating(function (Tag $tag): void {
            $tag->syncLegacyNameFromLocalized();
        });
    }

    /**
     * Mirrors {@see Category::syncLegacyNameFromLocalized()}.
     * Writes through `setAttribute()` to bypass the magic
     * `getNameAttribute()` accessor (the accessor would otherwise
     * fire on the read of `$this->name` and mask the assignment).
     */
    private function syncLegacyNameFromLocalized(): void
    {
        $rawName = $this->getAttributes()['name'] ?? null;
        $rawNamePt = $this->getAttributes()['name_pt'] ?? null;
        if (empty($rawName) && ! empty($rawNamePt)) {
            $this->setAttribute('name', $rawNamePt);
        }
    }
}
