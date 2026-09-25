<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateStudentRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'dob' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'string', 'in:male,female,other'],
            'blood_group' => ['required', 'string', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'class_division_id' => ['required', 'integer', 'exists:divisions,id'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'postal' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('login', 'email')->ignore($student?->login_id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['dob' => 'date of birth', 'class_division_id' => 'class / division'];
    }
}
