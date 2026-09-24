<?php

return [
    /*
    |--------------------------------------------------------------------------
    | School week
    |--------------------------------------------------------------------------
    |
    | ISO day numbers (1 = Monday ... 7 = Sunday) the timetable covers.
    |
    */

    'days' => array_map('intval', explode(',', env('SCHOOL_DAYS', '1,2,3,4,5,6'))),

    /*
    |--------------------------------------------------------------------------
    | Timetable generation
    |--------------------------------------------------------------------------
    */

    'timetable' => [
        // Most lessons of one subject a division gets on a single day.
        'max_subject_periods_per_day' => (int) env('TIMETABLE_MAX_SUBJECT_PER_DAY', 2),

        // Randomised attempts the generator makes; the best result is kept.
        'attempts' => (int) env('TIMETABLE_ATTEMPTS', 40),
    ],

    /*
    |--------------------------------------------------------------------------
    | Grade scale
    |--------------------------------------------------------------------------
    |
    | Minimum percentage for each grade, highest first. Anything below the
    | exam's pass mark is always an F.
    |
    */

    'grades' => [
        'A+' => 90,
        'A' => 80,
        'B' => 70,
        'C' => 60,
        'D' => 50,
        'E' => 0,
    ],
];
