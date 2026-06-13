<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the live 2FA challenge form.
 *
 * The user types a 6-digit TOTP code OR an 8–10 character
 * recovery code (with dashes). We accept either shape on a
 * single `code` field — the controller branches on format
 * (digits-only → TOTP, anything else → recovery code).
 *
 * `trust_device` is optional. The form's checkbox is a UX hint;
 * the server still decides whether to mint the cookie based on
 * the actual boolean value.
 */
class VerifyTwoFactorRequest extends FormRequest
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
            // The `code` field is dual-purpose: a 6-digit TOTP
            // (RFC 6238 default) OR a 12-char recovery code in
            // the `XXXX-XXXX-XX` format the enrollment service
            // mints. The controller branches on the actual
            // shape, so the validator only needs to enforce
            // the longest possible value (12 chars).
            'code' => ['required', 'string', 'min:6', 'max:12'],
            'trust_device' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Informe o código 2FA.',
            'code.min' => 'O código deve ter pelo menos :min caracteres.',
            'code.max' => 'O código deve ter no máximo :max caracteres.',
        ];
    }

    public function trustDevice(): bool
    {
        return $this->boolean('trust_device');
    }
}
