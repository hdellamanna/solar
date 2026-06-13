<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * One row per user that has 2FA enabled.
 *
 * The TOTP secret is stored encrypted-at-rest via the app key
 * (`Crypt::encryptString`); we need it back in plain form to verify
 * codes. `last_counter` is the 30-second time-step the user
 * successfully verified most recently, so the same step cannot be
 * redeemed twice (replay protection). `confirmed_at` is stamped the
 * first time the user passes the live challenge (kept for audit and
 * future UX, not currently used to gate anything).
 *
 * @property int $id
 * @property int $user_id
 * @property string $secret_encrypted
 * @property int $last_counter
 * @property \Illuminate\Support\Carbon $enabled_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 */
class UserTwoFactor extends Model
{
    /** @use HasFactory<\Database\Factories\UserTwoFactorFactory> */
    use HasFactory;

    /**
     * The table is named in the singular (`user_two_factor`) on
     * purpose — the design doc specifies this name to keep it
     * consistent with the `email_verification_tokens` family of
     * "one row per user" tables. Eloquent's pluraliser would
     * otherwise guess `user_two_factors` (an extra `s`).
     */
    protected $table = 'user_two_factor';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'secret_encrypted',
        'last_counter',
        'enabled_at',
        'confirmed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'last_counter' => 'integer',
        'enabled_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Decrypted plain-text TOTP secret. Accessor is the only place
     * the plain value is ever materialised; the service layer is
     * responsible for not logging or persisting it.
     */
    protected function secret(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Crypt::decryptString($this->secret_encrypted),
        );
    }
}
