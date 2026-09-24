<?php

namespace App\Http\Requests;

use App\Models\Login;
use App\Pipelines\SanitizeInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request for admin forms: admin-only, and text input is sanitized
 * before validation (except the fields listed in $unsanitized).
 */
abstract class AdminFormRequest extends FormRequest
{
    /**
     * Fields that must reach validation untouched (sanitizing would alter them).
     *
     * @var list<string>
     */
    protected array $unsanitized = ['password', 'parent_password'];

    public function authorize(): bool
    {
        return $this->user()?->role === Login::ROLE_ADMIN;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(SanitizeInput::run($this->except($this->unsanitized)));
    }
}
