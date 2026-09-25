<?php

namespace App\Http\Requests;

use App\Models\AcademicYear;
use App\Rules\UniqueCaseInsensitive;

class ExamRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $exam = $this->route('exam');
        $academicYearId = $exam?->academic_year_id ?? AcademicYear::current()?->id;

        return [
            'name' => ['required', 'string', 'max:100', new UniqueCaseInsensitive('exams', 'name', $exam?->id, ['academic_year_id' => $academicYearId])],
            'starts_on' => ['nullable', 'date'],
            'max_marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'pass_marks' => ['required', 'integer', 'min:0', 'lte:max_marks'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['pass_marks.lte' => 'The pass mark cannot be higher than the maximum marks.'];
    }
}
