<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams CSV downloads. Every export goes through here so that exported text can never run as a formula when
 * staff open the file in Excel or Google Sheets (Security SEC-12).
 */
class CsvExport
{
    /**
     * First characters that make a spreadsheet read a cell as a formula.
     */
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param  list<string>  $header
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_map(self::escapeCell(...), $header));

            foreach ($rows as $row) {
                fputcsv($out, array_map(self::escapeCell(...), $row));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Put a single quote in front of text that a spreadsheet would run as a formula, so it shows as typed.
     * Numbers can't be formulas and stay numbers.
     */
    public static function escapeCell(string|int|float|null $value): string|int|float|null
    {
        if (is_string($value) && $value !== '' && in_array($value[0], self::FORMULA_STARTS, true)) {
            return "'".$value;
        }

        return $value;
    }
}
