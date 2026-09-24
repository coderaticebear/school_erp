<?php

namespace App\Http\Requests;

use App\Rules\UniqueCaseInsensitive;

class DivisionRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $division = $this->route('division');
        $classId = $division?->class_id ?? (int) $this->input('class_id');

        return [
            'class_id' => [$division ? 'exclude' : 'required', 'integer', 'exists:classes,id'],
            'division_name' => [
                'required', 'string', 'max:10',
                new UniqueCaseInsensitive('divisions', 'division_name', $division?->id, ['class_id' => $classId]),
            ],
        ];
    }
}
