<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the "begin 2FA disable" form. The user types
 * their account password; the service layer verifies the hash
 * via `Hash::check` against `users.password`.
 *
 * Auth is required (this is a settings-page action, not the
 * email-link action). Defence in depth: a stolen cookie alone
 * cannot turn 2FA off without the password.
 */
class BeginTwoFactorDisableRequest extends FormRequest
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
            'password' => ['required', 'current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Informe sua senha.',
            'password.current_password' => 'Senha incorreta.',
        ];
    }
}
