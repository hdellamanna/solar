<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for a saved PIX key (FASE 4C).
 *
 * Lets the user keep a small address book of keys they regularly
 * use, with one flagged as primary. Used by the dedicated PIX UI
 * for quick BR Code generation and by the transaction form for
 * autocomplete.
 *
 * @property int $id
 * @property int $user_id
 * @property string $label
 * @property string $key
 * @property string $type "cpf|cnpj|email|phone|evp"
 * @property bool $is_primary
 */
class PixKey extends Model
{
    /** @use HasFactory<\Database\Factories\PixKeyFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'label', 'key', 'type', 'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public const TYPES = [
        'cpf' => 'CPF',
        'cnpj' => 'CNPJ',
        'email' => 'E-mail',
        'phone' => 'Telefone',
        'evp' => 'Chave aleatória',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
