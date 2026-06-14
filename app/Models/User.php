<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'theme',
        'home_currency',
        'use_ai_categorize',
        'motion_preference',
        'motion_backdrop',
        'motion_spring',
        'motion_parallax',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'use_ai_categorize' => 'boolean',
            'last_ai_suggestion_at' => 'datetime',
            'motion_backdrop' => 'boolean',
            'motion_spring' => 'boolean',
            'motion_parallax' => 'boolean',
        ];
    }

    /** Default ISO-4217 home currency (3-letter). FASE 6A. */
    public function getHomeCurrencyAttribute(): string
    {
        return strtoupper($this->attributes['home_currency'] ?? 'BRL');
    }

    /**
     * Accounts owned by this user.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Custom categories created by this user.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * All transactions owned by this user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Recurrence rules owned by this user.
     */
    public function recurrences(): HasMany
    {
        return $this->hasMany(Recurrence::class);
    }

    /**
     * Investment positions owned by this user (FASE 5).
     */
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    /**
     * Debts owned by this user (FASE 5).
     */
    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    /**
     * Email verification tokens issued to this user (FASE 4D / Auth Phase 1).
     */
    public function emailVerificationTokens(): HasMany
    {
        return $this->hasMany(EmailVerificationToken::class);
    }

    /**
     * 2FA enrolment row (Auth Phase 3). A user either has one row
     * (2FA enabled) or none.
     */
    public function twoFactor(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserTwoFactor::class);
    }

    /**
     * Recovery codes (Auth Phase 3). Up to 10 per user, marked
     * `consumed_at` after redemption.
     */
    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(RecoveryCode::class);
    }

    /**
     * Trusted-device cookies (Auth Phase 3). Up to N, capped by
     * the user's own behaviour; not actively capped by the app.
     */
    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    /**
     * True when the user has completed 2FA enrollment (a row in
     * `user_two_factor` exists and is not soft-deleted — the table
     * does not soft-delete, the row just gets dropped on disable).
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->twoFactor !== null;
    }

    /**
     * True when the user has satisfied the 2FA challenge in the
     * current session, OR has a trusted-device cookie that the
     * middleware validated. Backed by a session key so it
     * automatically expires when the session does.
     */
    public function isTwoFactorVerified(): bool
    {
        return (bool) session('two_factor_verified');
    }

    /**
     * Stamp the session so the user is no longer challenged until
     * they log out or the session expires.
     */
    public function markTwoFactorVerified(): void
    {
        session(['two_factor_verified' => true]);
    }

    /**
     * Clear the session flag — used on disable, on logout, and in
     * tests.
     */
    public function clearTwoFactorVerified(): void
    {
        session()->forget('two_factor_verified');
    }

    /**
     * Initials used for the avatar in the top bar.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '?', 0, 1);
        $last = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);

        return mb_strtoupper($first.$last);
    }
}
