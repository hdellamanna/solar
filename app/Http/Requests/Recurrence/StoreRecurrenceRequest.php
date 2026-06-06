<?php

namespace App\Http\Requests\Recurrence;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'description' => 'required|string|max:255',
            'amount_cents' => 'required|integer|min:1',
            'type' => 'required|in:income,expense,transfer',
            'frequency' => 'required|in:daily,weekly,monthly,yearly',
            'account_id' => 'required|exists:accounts,id',
            'category_id' => 'nullable|exists:categories,id',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'active' => 'boolean',
        ];
    }
}
