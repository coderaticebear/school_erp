<?php

namespace App\Http\Requests;

use App\Models\Login;
use App\Models\Teachers;
use App\Models\TimetableEntry;
use Illuminate\Validation\Validator;

class TimetableEntryRequest extends ClearTimetableEntryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
        ];
    }

    /**
     * The teacher must be active, assigned to the division, teach the subject, and be free in that slot.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $teacher = Teachers::with('login')->find($this->integer('teacher_id'));

                if ($teacher->login?->role !== Login::ROLE_TEACHER || ! $teacher->login->is_active) {
                    $validator->errors()->add('teacher_id', "{$teacher->full_name} is not an active teacher.");

                    return;
                }

                if (! $teacher->divisions()->whereKey($this->integer('division_id'))->exists()) {
                    $validator->errors()->add('teacher_id', "{$teacher->full_name} is not assigned to this division.");
                }

                if (! $teacher->subjects()->whereKey($this->integer('subject_id'))->exists()) {
                    $validator->errors()->add('subject_id', "{$teacher->full_name} does not teach this subject.");
                }

                $clash = TimetableEntry::query()
                    ->with('division.class')
                    ->where('academic_year_id', $this->academicYear()->id)
                    ->where('teacher_id', $teacher->id)
                    ->where('day', $this->integer('day'))
                    ->where('period_id', $this->integer('period_id'))
                    ->where('division_id', '!=', $this->integer('division_id'))
                    ->first();

                if ($clash) {
                    $validator->errors()->add('teacher_id', "{$teacher->full_name} is already teaching {$clash->division->label} in that period.");
                }
            },
        ];
    }
}
