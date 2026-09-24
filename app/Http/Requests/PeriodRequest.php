<?php

namespace App\Http\Requests;

use App\Models\Period;
use Illuminate\Validation\Validator;

class PeriodRequest extends AdminFormRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge(['is_break' => $this->boolean('is_break')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:50'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'is_break' => ['boolean'],
        ];
    }

    /**
     * Periods may not overlap each other.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $overlap = Period::query()
                    ->when($this->route('period'), fn ($query, $period) => $query->whereKeyNot($period->id))
                    ->where('starts_at', '<', $this->input('ends_at'))
                    ->where('ends_at', '>', $this->input('starts_at'))
                    ->first();

                if ($overlap) {
                    $validator->errors()->add('starts_at', "This time overlaps {$overlap->label} ({$overlap->time_range}).");
                }
            },
        ];
    }
}
