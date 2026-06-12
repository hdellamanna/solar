<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for the "resend verification email" action.
 *
 * Validation is intentionally a no-op — the route itself is guarded
 * by the `auth` middleware, and the throttling lives in the service
 * layer. We keep this as a FormRequest so the controller signature
 * stays consistent and so future additions (e.g. a captcha) land in
 * a familiar place.
 */
class ResendVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
