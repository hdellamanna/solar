<?php

namespace App\Http\Requests\Goal;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:80'],
            'target_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'icon' => ['nullable', 'string', 'max:8'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Dê um nome à meta.',
            'target_amount.required' => 'Informe o valor alvo.',
            'target_amount.min' => 'O valor alvo deve ser maior que zero.',
            'deadline.after_or_equal' => 'O prazo não pode ser no passado.',
            'color.regex' => 'Use uma cor no formato #RRGGBB.',
        ];
    }
}
