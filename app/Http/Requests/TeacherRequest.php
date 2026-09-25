<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class TeacherRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teacher = $this->route('teacher');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'postal' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('login', 'email')->ignore($teacher?->login_id)],
            // Required when creating; optional when editing (blank keeps the current password).
            'password' => [$teacher ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['integer', 'distinct', 'exists:subjects,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['subject_ids.required' => 'Choose at least one subject.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['subject_ids' => 'subjects'];
    }
}
