<?php

namespace App\Http\Requests\Investment;

use App\Models\Investment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation rules for updating an existing investment.
 *
 * Identical to {@see StoreInvestmentRequest} — kept as a separate
 * class to leave room for divergence later (e.g. partial updates).
 */
class UpdateInvestmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'min:1', 'max:100'],
            'type'         => ['required', 'string', Rule::in(array_keys(Investment::TYPES))],
            'ticker'       => ['nullable', 'string', 'max:20'],
            'quantity'     => ['required', 'numeric', 'gt:0', 'max:999999999.99999999'],
            'average_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'current_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency'     => ['required', 'string', 'size:3'],
            'acquired_at'  => ['nullable', 'date'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Dê um nome ao investimento.',
            'type.required'      => 'Escolha o tipo do investimento.',
            'type.in'            => 'Tipo de investimento inválido.',
            'quantity.required'  => 'Informe a quantidade.',
            'quantity.gt'        => 'A quantidade deve ser maior que zero.',
            'average_price.required' => 'Informe o preço médio.',
            'average_price.min'  => 'O preço médio não pode ser negativo.',
            'current_price.min'  => 'O preço atual não pode ser negativo.',
            'currency.size'      => 'A moeda deve ter 3 letras (ex: BRL, USD).',
        ];
    }
}
