<?php

namespace App\Http\Requests\Goal;

use Illuminate\Foundation\Http\FormRequest;

class ContributeGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Positive int in cents (e.g. 5000 = R$ 50,00)
            'amount_cents' => ['required', 'integer', 'min:1', 'max:9999999999'],
        ];
    }
}
