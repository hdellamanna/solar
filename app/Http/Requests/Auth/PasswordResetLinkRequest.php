<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the "forgot password" form.
 *
 * Authorisation is a no-op — the route is behind the `guest`
 * middleware, and the controller does not need a session. Throttling
 * lives in {@see \App\Services\Auth\PasswordResetService::canRequestReset()}.
 */
class PasswordResetLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'E-mail muito longo.',
        ];
    }
}
