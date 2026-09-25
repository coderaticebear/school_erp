<?php

namespace App\Http\Requests;

use App\Rules\UniqueCaseInsensitive;

class SubjectRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject_name' => [
                'required', 'string', 'max:255',
                new UniqueCaseInsensitive('subjects', 'subject_name', $this->route('subject')?->id),
            ],
            'periods_per_week' => ['nullable', 'integer', 'min:1', 'max:60'],
        ];
    }
}
