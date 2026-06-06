<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:income,expense,transfer',
            'account_id' => 'required|exists:accounts,id',
            'destination_account_id' => 'nullable|required_if:type,transfer|exists:accounts,id|different:account_id',
            'category_id' => 'nullable|exists:categories,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'required|in:paid,pending',
            'is_pix' => 'boolean',
            'pix_key' => 'nullable|string|max:255',

            // Splits (FASE 3B)
            'splits' => 'sometimes|array|min:2',
            'splits.*.user_id' => 'required_with:splits|integer|exists:users,id',
            'splits.*.category_id' => 'nullable|integer|exists:categories,id',
            'splits.*.amount_cents' => 'required_with:splits|integer',
            'splits.*.description' => 'nullable|string|max:255',
            'splits.*.paid_by_user_id' => 'nullable|integer|exists:users,id',
        ];
    }
}
