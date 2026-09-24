<?php

namespace App\Http\Requests;

use App\Models\AcademicYear;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClearTimetableEntryRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'day' => ['required', 'integer', Rule::in(config('school.days'))],
            'period_id' => ['required', 'integer', Rule::exists('periods', 'id')->where(fn ($query) => $query->where('is_break', false))],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->academicYear()) {
                    $validator->errors()->add('division_id', 'Activate an academic year before editing the timetable.');
                }
            },
        ];
    }

    public function academicYear(): ?AcademicYear
    {
        return once(fn () => AcademicYear::current());
    }
}
