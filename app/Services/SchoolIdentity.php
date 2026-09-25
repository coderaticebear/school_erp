<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * The school's name and crest initials, from config/school.php.
 */
class SchoolIdentity
{
    /**
     * Words skipped when deriving initials ("School of the Arts" → "SA").
     *
     * @var list<string>
     */
    protected const MINOR_WORDS = ['of', 'the', 'and', 'for', 'at', 'in', 'a', 'an', '&'];

    public static function name(): string
    {
        return (string) (config('school.name') ?: 'School ERP');
    }

    /**
     * Up to two letters for the crest.
     */
    public static function initials(): string
    {
        $configured = trim((string) config('school.initials'));

        if ($configured !== '') {
            return Str::upper(Str::substr($configured, 0, 2));
        }

        $words = collect(preg_split('/\s+/u', self::name()))
            ->filter(fn (string $word) => $word !== '' && ! in_array(Str::lower($word), self::MINOR_WORDS, true))
            ->values();

        $letters = $words->take(2)->map(fn (string $word) => Str::substr(preg_replace('/[^\pL\pN]/u', '', $word), 0, 1));

        return Str::upper($letters->implode('')) ?: 'S';
    }
}
