<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the `POST /two-factor/disable/confirm` form
 * (Auth Phase 3). Carries the raw disable token from the
 * email link plus the user's password (re-prompted, defence
 * in depth).
 *
 * No auth required — the token IS the credential. The service
 * layer re-validates token lifetime / consumed state AND
 * re-checks the password hash.
 */
class ConfirmTwoFactorDisableRequest extends FormRequest
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
            'password' => ['required', 'current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Token ausente.',
            'password.required' => 'Informe sua senha.',
            'password.current_password' => 'Senha incorreta.',
        ];
    }
}
