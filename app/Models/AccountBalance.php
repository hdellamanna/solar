<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sub-balance of an account in a specific currency (FASE 6A).
 *
 * Used by multi-currency accounts (Wise, Nomad Global, C6 Global,
 * Inter Global) where one account holds balances in several
 * currencies at once. The "home" currency of the account still
 * lives in `accounts.initial_balance_cents`; every other currency
 * the user has added to the account is stored here.
 *
 * @property int $id
 * @property int $account_id
 * @property string $currency ISO-4217 3-letter code (BRL, USD, EUR, GBP, ...)
 * @property int $balance_cents Signed integer in cents
 */
class AccountBalance extends Model
{
    /** @use HasFactory<\Database\Factories\AccountBalanceFactory> */
    use HasFactory;

    protected $fillable = ['account_id', 'currency', 'balance_cents'];

    protected $casts = [
        'balance_cents' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
