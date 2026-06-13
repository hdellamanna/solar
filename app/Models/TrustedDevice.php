<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A trusted-device cookie issued during a successful 2FA challenge.
 *
 * The cookie carries `selector:validator`. The `selector` is the
 * public lookup key (random 32 bytes, base64url-encoded) and lives
 * in the cookie in clear text. The `validator` is the secret proof
 * of possession (random 64 bytes, base64url-encoded) and is only
 * stored hashed (SHA-256) on the server. Same scheme Laravel uses
 * for `remember_token` — see PersonalAccessTokenResult for the
 * original inspiration.
 *
 * `last_seen_at` is updated on every successful verify, so the
 * settings page can show "last used". `expires_at` is 90 days from
 * issue. Expired rows are cleaned up daily by the scheduler via
 * {@see \App\Services\Auth\TrustedDeviceService::cleanup()}.
 *
 * @property int $id
 * @property int $user_id
 * @property string $selector
 * @property string $validator_hash
 * @property string|null $friendly_name
 * @property string|null $ip
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $last_seen_at
 * @property \Illuminate\Support\Carbon $expires_at
 */
class TrustedDevice extends Model
{
    /** @use HasFactory<\Database\Factories\TrustedDeviceFactory> */
    use HasFactory;

    /**
     * Explicit table name. The pluraliser would guess the right
     * one for `trusted_devices` but we set it anyway so the
     * model is robust against future renames.
     */
    protected $table = 'trusted_devices';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'selector',
        'validator_hash',
        'friendly_name',
        'ip',
        'user_agent',
        'last_seen_at',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'last_seen_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() === true;
    }

    /**
     * Bump the `last_seen_at` marker. Cheap, called on every
     * successful cookie verify (so the "last used" column on the
     * settings page stays roughly accurate).
     */
    public function touchSeen(): void
    {
        $this->last_seen_at = now();
        $this->save();
    }

    /**
     * Constant-time SHA-256 comparison. The caller supplies the
     * raw `validator` from the cookie; we hash it and compare
     * against the stored `validator_hash`. Returns false on
     * mismatch OR expiry (the caller treats both the same).
     */
    public function verify(string $rawValidator): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        return hash_equals($this->validator_hash, hash('sha256', $rawValidator));
    }
}
