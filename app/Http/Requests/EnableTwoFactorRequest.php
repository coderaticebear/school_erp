<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The first code from the authenticator app, which turns on two-factor (SEC-06). The code is not sanitized:
 * TwoFactorAuth::verifyCode() accepts only 6 digits.
 */
class EnableTwoFactorRequest extends FormRequest
{
    /**
     * Every signed-in user may set up two-factor. The route middleware checks the sign-in.
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
            'code' => ['required', 'string', 'max:32'],
        ];
    }
}
