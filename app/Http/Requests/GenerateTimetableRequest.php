<?php

namespace App\Http\Requests;

class GenerateTimetableRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Empty means every division.
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
        ];
    }
}
