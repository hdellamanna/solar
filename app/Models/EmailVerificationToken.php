<?php

namespace App\Models;

use Database\Factories\EmailVerificationTokenFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Eloquent model representing a single-use bearer token.
 *
 * Originally the table only carried email-verification tokens (FASE 4D
 * / Auth Phase 1). As of Auth Phase 2 the same table also carries
 * password-reset tokens; the `purpose` column is the discriminator.
 * The class and table names are kept historical — see the design doc
 * for the reasoning.
 *
 * Tokens are stored as a SHA-256 hash of a 64-char random string. The
 * raw token is only ever returned by `generateForUser()` and is meant
 * to be embedded in the verification / reset email immediately. We
 * never log or persist the raw value.
 *
 * @property int $id
 * @property int $user_id
 * @property string $purpose
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

    /** Token issued for the email verification flow. */
    public const PURPOSE_EMAIL_VERIFICATION = 'email_verification';

    /** Token issued for the password reset flow. */
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    /** Token issued to confirm 2FA enrollment (Auth Phase 3). */
    public const PURPOSE_TWO_FACTOR_ENROLL = 'two_factor_enroll';

    /** Token issued to confirm 2FA disable (Auth Phase 3). */
    public const PURPOSE_TWO_FACTOR_DISABLE = 'two_factor_disable';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'purpose',
        'token_hash',
        'expires_at',
        'consumed_at',
        'ip_address',
        'user_agent',
        'meta',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'meta' => 'array',
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
     * Filter the query to only tokens minted for a given purpose
     * (`email_verification` or `password_reset`). Use this from the
     * service layer to keep lookups purpose-aware — mixing the two
     * purposes in a single query is almost always a bug.
     */
    public function scopeForPurpose(Builder $query, string $purpose): Builder
    {
        return $query->where('purpose', $purpose);
    }

    /**
     * Generate a brand-new random token, persist the hash for the given
     * user, and return both the raw token (so the caller can embed it
     * in an email) and the persisted model.
     *
     * @return array{token: string, model: self}
     */
    public static function generateForUser(
        User $user,
        ?Carbon $expiresAt = null,
        ?string $ip = null,
        ?string $ua = null,
        string $purpose = self::PURPOSE_EMAIL_VERIFICATION,
    ): array {
        $raw = Str::random(self::TOKEN_LENGTH);
        $model = self::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'token_hash' => self::hashToken($raw),
            'expires_at' => $expiresAt ?? now()->addMinutes(self::TTL_MINUTES),
            'ip_address' => $ip,
            'user_agent' => $ua !== null ? mb_substr($ua, 0, 255) : null,
        ]);

        return ['token' => $raw, 'model' => $model];
    }
}
