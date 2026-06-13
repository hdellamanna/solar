<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single-use recovery code for a 2FA-enabled user.
 *
 * Codes are generated as 10-char random strings (uppercase + digits)
 * and stored only as their SHA-256 hash. On redeem, the live
 * challenge hashes the user-supplied value and looks it up by
 * `(user_id, code_hash)` where `consumed_at IS NULL`. The first
 * matching row is consumed in the same transaction.
 *
 * The plain code is never persisted, never logged, and is shown
 * to the user exactly once (right after 2FA enrollment).
 *
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property \Illuminate\Support\Carbon|null $consumed_at
 */
class RecoveryCode extends Model
{
    /** @use HasFactory<\Database\Factories\RecoveryCodeFactory> */
    use HasFactory;

    /**
     * Explicit table name — the design uses the `user_*` prefix
     * on every auth-related table. Eloquent's pluraliser would
     * otherwise guess `recovery_codes` and miss the prefix.
     */
    protected $table = 'user_recovery_codes';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'code_hash',
        'consumed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'consumed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function markConsumed(): void
    {
        $this->consumed_at = now();
        $this->save();
    }
}
