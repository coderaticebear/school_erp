<?php

namespace App\Http\Requests;

class StoreStudentRequest extends AdminFormRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // The form always posts the hidden parent_id; an empty one means "create a new parent".
        if (blank($this->input('parent_id'))) {
            $this->request->remove('parent_id');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
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

            'email' => ['required', 'email', 'max:255', 'unique:login,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],

            'parent_id' => ['nullable', 'integer', 'exists:parents,id'],
            'parent_first_name' => ['exclude_with:parent_id', 'required', 'string', 'max:255'],
            'parent_last_name' => ['exclude_with:parent_id', 'required', 'string', 'max:255'],
            'parent_area_code' => ['exclude_with:parent_id', 'required', 'string', 'max:10'],
            'parent_phone' => ['exclude_with:parent_id', 'required', 'string', 'max:10'],
            'p_email' => ['exclude_with:parent_id', 'required', 'email', 'max:255', 'unique:login,email', 'different:email'],
            'parent_password' => ['exclude_with:parent_id', 'required', 'string', 'min:8', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'class_division_id.required' => 'Please choose a class and division.',
            'p_email.different' => 'The parent email must be different from the student email.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'dob' => 'date of birth',
            'p_email' => 'parent email',
            'class_division_id' => 'class / division',
        ];
    }
}
