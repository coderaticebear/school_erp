<?php

namespace App\Http\Requests;

use App\Rules\UniqueCaseInsensitive;

class ClassRequest extends AdminFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'class_name' => [
                'required', 'string', 'max:10',
                new UniqueCaseInsensitive('classes', 'class_name', $this->route('class')?->id),
            ],
        ];
    }
}
