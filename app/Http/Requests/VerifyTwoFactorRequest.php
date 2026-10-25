<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A code from the authenticator app, or a recovery code, at the two-factor challenge (SEC-06). The codes are not
 * sanitized: TwoFactorAuth checks their form itself.
 */
class VerifyTwoFactorRequest extends FormRequest
{
    /**
     * Every signed-in user may answer the challenge. The route middleware checks the sign-in.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:32', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:32', 'required_without:code'],
        ];
    }
}
