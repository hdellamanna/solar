<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Transaction category. A null user_id means a global default category.
 *
 * FASE 7 — i18n: the category carries a `name` column (kept for
 * pre-FASE-7 query compatibility — see the
 * `add_localized_names_to_categories_table` migration for the rationale)
 * plus three localized variants:
 *  - `name_pt` (Portuguese, the source of truth for slug / display)
 *  - `name_es` (Spanish, nullable)
 *  - `name_en` (English, nullable)
 *
 * The `name` attribute is the magic accessor: it returns the active
 * locale's value (resolved from `app()->getLocale()`), falling back
 * through pt-BR → es → en → `#id`. The `name` column itself is kept
 * in sync with `name_pt` by the `creating` / `updating` model events
 * defined in `boot()`.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name         (legacy column; kept in sync with name_pt)
 * @property string|null $name_pt
 * @property string|null $name_es
 * @property string|null $name_en
 * @property string $type
 * @property string|null $icon
 * @property string|null $color
 * @property bool $is_default
 */
class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'name_pt',
        'name_es',
        'name_en',
        'type',
        'icon',
        'color',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Owner of the category (null when global).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Transactions associated with this category.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scope: only global default categories.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true)->whereNull('user_id');
    }

    /**
     * Scope: only user-created categories.
     */
    public function scopeCustom(Builder $query): Builder
    {
        return $query->whereNotNull('user_id')->where('is_default', false);
    }

    /**
     * Scope: filter by income or expense type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /** Scope used by SearchController: user's own categories + global defaults (user_id null). */
    public function scopeAccessibleBy(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhereNull('user_id');
        });
    }

    /**
     * FASE 7 — i18n accessor: returns the category's name in the
     * active locale, with a deterministic fallback chain.
     *
     * Resolution order:
     *   1. The current locale's column (`name_<short>` of `app()->getLocale()`)
     *   2. pt-BR (`name_pt`)
     *   3. es (`name_es`)
     *   4. en (`name_en`)
     *   5. The legacy `name` column (kept for pre-FASE-7 data and
     *      tests that seed via `'name' => $x` without populating
     *      the localized columns).
     *   6. The row's id (`#<id>`)
     *
     * `getLocale()` may return `pt-BR` (region-tagged) — we strip the
     * region (`-BR`, `-US`, etc.) so the lookup matches the column
     * shape (`name_pt`, `name_es`, `name_en`).
     */
    public function getNameAttribute(): string
    {
        $locale = (string) app()->getLocale();
        $short = strtolower(explode('-', $locale)[0] ?? 'pt'); // pt-BR -> pt
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
     * FASE 7 — i18n boot hook.
     *
     * Keeps the legacy `name` column in sync with `name_pt` so the
     * pre-FASE-7 query patterns (`where('name', $x)`,
     * `firstOrCreate(['name' => $x, 'user_id' => null])`) keep
     * matching, and so the AI categorizer's `category_name` field
     * stays consistent with the user-facing name.
     *
     * The synchronization is one-way: `name` is a derived copy of
     * `name_pt`, never an independent source. If a user creates a
     * category with only `name_es` set, `name_pt` is left null
     * (the accessor will fall back to `name_es`) and `name` is also
     * left null — this is intentional. The default-locale user
     * populates `name_pt`, and the validation rule on the form
     * requires at least one of the three localized fields to be
     * non-empty.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Category $category): void {
            $category->syncLegacyNameFromLocalized();
        });

        static::updating(function (Category $category): void {
            $category->syncLegacyNameFromLocalized();
        });
    }

    /**
     * If `name_pt` is set (or is the field being mutated) and
     * `name` is null, copy it. Leaves `name` untouched when the
     * caller already set both — the caller is the source of truth.
     *
     * We MUST write through `setAttribute('name', ...)` (or
     * `setRawAttributes()`) instead of `$this->name = ...` because
     * the magic `getNameAttribute()` accessor is invoked on every
     * `$this->name` read, including the right-hand-side of an
     * assignment — which would mask the write. `setAttribute` writes
     * the raw underlying value and skips the accessor.
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
