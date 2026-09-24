<?php

namespace App\Http\Requests;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Divisions;
use App\Models\StudentClass;
use App\Pipelines\SanitizeInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $division = Divisions::find($this->integer('division_id'));

        // Unknown divisions fall through to validation (exists rule) rather than a 403.
        return ! $division || Gate::allows('teach-division', $division);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'attendance' => collect($this->input('attendance', []))
                ->map(fn ($row) => is_array($row) ? SanitizeInput::run($row) : $row)
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'attendance' => ['required', 'array', 'min:1'],
            'attendance.*.status' => ['required', Rule::in(array_keys(Attendance::STATUSES))],
            'attendance.*.remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $this->academicYear()) {
                    $validator->errors()->add('date', 'There is no active academic year.');

                    return;
                }

                if (! in_array(Carbon::parse($this->input('date'))->isoWeekday(), config('school.days'), true)) {
                    $validator->errors()->add('date', 'That date is not a school day.');
                }

                $enrolled = StudentClass::query()
                    ->where('class_division_id', $this->integer('division_id'))
                    ->where('academic_year_id', $this->academicYear()->id)
                    ->pluck('student_id')
                    ->map(fn ($id) => (int) $id);

                $unknown = collect(array_keys($this->input('attendance')))->map(fn ($id) => (int) $id)->diff($enrolled);

                if ($unknown->isNotEmpty()) {
                    $validator->errors()->add('attendance', 'Some students are not enrolled in this division.');
                }
            },
        ];
    }

    public function academicYear(): ?AcademicYear
    {
        return once(fn () => AcademicYear::current());
    }
}
