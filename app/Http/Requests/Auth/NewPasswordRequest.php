<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validation for the "set a new password" form.
 *
 * Carries the raw reset token (looked up server-side via the SHA-256
 * hash on the model) and the new password (with a confirmation
 * field, same shape as registration). Authorisation is a no-op —
 * the route is behind the `guest` middleware, and the actual token
 * validity check lives in
 * {@see \App\Services\Auth\PasswordResetService::resetPassword()}.
 */
class NewPasswordRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Token ausente ou inválido.',
            'password.required' => 'Informe uma senha.',
            'password.confirmed' => 'A confirmação de senha não confere.',
            'password.min' => 'A senha deve ter pelo menos :min caracteres.',
        ];
    }
}
