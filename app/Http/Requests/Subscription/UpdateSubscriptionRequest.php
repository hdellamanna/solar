<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => ['required', 'string', 'min:1', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'billing_day' => ['required', 'integer', 'min:1', 'max:31'],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('user_id', $userId)],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })],
            'icon' => ['nullable', 'string', 'max:8'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
