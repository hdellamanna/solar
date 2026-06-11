<?php

namespace App\Http\Requests\Debt;

use App\Models\Debt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the `PUT/PATCH /debts/{debt}` endpoint (FASE 5).
 *
 * Same rules as {@see StoreDebtRequest} — kept in sync.
 */
class UpdateDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'creditor' => ['required', 'string', 'min:1', 'max:80'],
            'description' => ['nullable', 'string', 'max:120'],
            'total_balance' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'interest_rate' => ['required', 'numeric', 'gte:0', 'max:9999.99'],
            'monthly_payment' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'start_date' => ['required', 'date'],
            'payoff_strategy' => ['required', Rule::in([Debt::STRATEGY_SAC, Debt::STRATEGY_PRICE])],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'total_balance.gt' => 'O saldo total precisa ser maior que zero.',
            'interest_rate.gte' => 'A taxa de juros não pode ser negativa.',
            'monthly_payment.gt' => 'A parcela mensal precisa ser maior que zero.',
            'payoff_strategy.in' => 'Estratégia de amortização inválida (use SAC ou Price).',
            'currency.regex' => 'A moeda precisa ser um código ISO-4217 de 3 letras (ex: BRL).',
        ];
    }
}
