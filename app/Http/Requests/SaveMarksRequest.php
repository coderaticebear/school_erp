<?php

namespace App\Http\Requests;

use App\Models\StudentClass;
use App\Pipelines\SanitizeInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class SaveMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('enter-marks', [$this->route('division'), $this->route('subject')]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'marks' => collect($this->input('marks', []))
                ->map(fn ($row) => is_array($row) ? [...SanitizeInput::run($row), 'absent' => ! empty($row['absent'])] : $row)
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = $this->route('exam')->max_marks;

        return [
            'marks' => ['required', 'array'],
            'marks.*.value' => ['nullable', 'numeric', 'min:0', "max:{$max}", 'decimal:0,2'],
            'marks.*.absent' => ['boolean'],
            'marks.*.remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'marks.*.value.max' => 'Marks cannot be more than :max.',
            'marks.*.value.min' => 'Marks cannot be negative.',
            'marks.*.value.numeric' => 'Marks must be a number.',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $exam = $this->route('exam');

                if ($exam->isPublished()) {
                    $validator->errors()->add('marks', 'Results for this exam are published. Ask the office to unpublish them before changing marks.');

                    return;
                }

                $enrolled = StudentClass::query()
                    ->where('class_division_id', $this->route('division')->id)
                    ->where('academic_year_id', $exam->academic_year_id)
                    ->pluck('student_id')
                    ->map(fn ($id) => (int) $id);

                if (collect(array_keys($this->input('marks', [])))->map(fn ($id) => (int) $id)->diff($enrolled)->isNotEmpty()) {
                    $validator->errors()->add('marks', 'Some students are not enrolled in this division.');
                }

                foreach ($this->input('marks', []) as $studentId => $row) {
                    if (! empty($row['absent']) && filled($row['value'] ?? null)) {
                        $validator->errors()->add("marks.{$studentId}.value", 'A student marked absent cannot also have marks.');
                    }
                }
            },
        ];
    }
}
