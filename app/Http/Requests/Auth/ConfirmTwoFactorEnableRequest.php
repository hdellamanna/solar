<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the `POST /two-factor/enable/confirm` form
 * (Auth Phase 3). Carries the raw enrollment token from the
 * email link plus the 6-digit TOTP code the user typed after
 * scanning the QR.
 *
 * No auth required — the token IS the credential. The service
 * layer re-validates token lifetime, consumed state, and TOTP
 * match.
 */
class ConfirmTwoFactorEnableRequest extends FormRequest
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
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Token ausente.',
            'code.required' => 'Informe o código de 6 dígitos.',
            'code.digits' => 'O código deve ter exatamente 6 dígitos.',
        ];
    }
}
