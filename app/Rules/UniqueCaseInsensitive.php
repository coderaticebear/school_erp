<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Case-insensitive "unique" check, matching the lower(...) unique indexes in Postgres.
 */
class UniqueCaseInsensitive implements ValidationRule
{
    /**
     * @param  array<string, mixed>  $scope  extra column => value conditions (e.g. ['class_id' => 3])
     */
    public function __construct(
        protected string $table,
        protected string $column,
        protected ?int $ignoreId = null,
        protected array $scope = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = DB::table($this->table)
            ->whereRaw("lower({$this->column}) = ?", [mb_strtolower((string) $value)])
            ->where($this->scope)
            ->when($this->ignoreId, fn ($query) => $query->where('id', '!=', $this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('The :attribute has already been taken.');
        }
    }
}
