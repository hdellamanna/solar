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
            // The service layer re-checks the password against the
            // user identified by the token — `current_password`
            // would not work here because the user is NOT
            // authenticated at this point (the email link IS the
            // credential, the user may have clicked it on a
            // different device).
            'password' => ['required', 'string', 'min:1'],
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
