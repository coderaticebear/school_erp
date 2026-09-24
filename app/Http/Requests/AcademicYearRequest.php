<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AcademicYearRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'year' => [
                'required', 'string', 'regex:/^\d{4}-\d{4}$/',
                Rule::unique('academic_year', 'year')->ignore($this->route('academicYear')),
                function (string $attribute, mixed $value, \Closure $fail) {
                    [$start, $end] = array_map('intval', explode('-', (string) $value) + [1 => 0]);

                    if ($end !== $start + 1) {
                        $fail('The academic year must span two consecutive years, e.g. 2025-2026.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['year.regex' => 'Use the format YYYY-YYYY, e.g. 2025-2026.'];
    }
}
