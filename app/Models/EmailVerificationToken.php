<?php

namespace App\Models;

use Database\Factories\EmailVerificationTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Eloquent model representing a single-use email verification token.
 *
 * Tokens are stored as a SHA-256 hash of a 64-char random string. The raw
 * token is only ever returned by `generateForUser()` and is meant to be
 * embedded in the verification email immediately. We never log or persist
 * the raw value.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class EmailVerificationToken extends Model
{
    /** @use HasFactory<EmailVerificationTokenFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'consumed_at',
        'ip_address',
        'user_agent',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    /**
     * Default lifetime for a freshly issued token (60 minutes per the
     * FASE 4D design doc).
     */
    public const TTL_MINUTES = 60;

    /**
     * Length of the random token, in characters. 64 hex chars = 256 bits
     * of entropy, comfortably more than enough for a single-use bearer.
     */
    public const TOKEN_LENGTH = 64;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A token is valid when it has not been consumed and has not yet
     * expired.
     */
    public function isValid(): bool
    {
        return $this->consumed_at === null && $this->expires_at?->isFuture() === true;
    }

    /**
     * SHA-256 hash the supplied raw token. Use this when looking up by
     * the value the user clicked in the email.
     */
    public static function hashToken(string $raw): string
    {
        return hash('sha256', $raw);
    }

    /**
     * Generate a brand-new random token, persist the hash for the given
     * user, and return both the raw token (so the caller can embed it
     * in an email) and the persisted model.
     *
     * @return array{token: string, model: self}
     */
    public static function generateForUser(User $user, ?Carbon $expiresAt = null, ?string $ip = null, ?string $ua = null): array
    {
        $raw = Str::random(self::TOKEN_LENGTH);
        $model = self::create([
            'user_id' => $user->id,
            'token_hash' => self::hashToken($raw),
            'expires_at' => $expiresAt ?? now()->addMinutes(self::TTL_MINUTES),
            'ip_address' => $ip,
            'user_agent' => $ua !== null ? mb_substr($ua, 0, 255) : null,
        ]);

        return ['token' => $raw, 'model' => $model];
    }
}
