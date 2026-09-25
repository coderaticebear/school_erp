<?php

namespace App\Http\Requests;

class DivisionTeachersRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'teacher_ids' => ['nullable', 'array'],
            'teacher_ids.*' => ['integer', 'distinct', 'exists:teachers,id'],
            'class_teacher_id' => ['nullable', 'integer', 'in_array:teacher_ids.*'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['class_teacher_id.in_array' => 'The class teacher must be one of the teachers assigned to this division.'];
    }
}
