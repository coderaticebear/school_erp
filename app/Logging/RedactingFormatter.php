<?php

namespace App\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/**
 * Wraps a log channel's formatter and masks personal data in everything it writes (SEC-04).
 *
 * It works on the finished text, so exception messages, their previous exceptions and the
 * context are all covered, whatever produced them.
 */
class RedactingFormatter implements FormatterInterface
{
    /**
     * Pattern => replacement, applied in order.
     *
     * @var array<string, string>
     */
    public const PATTERNS = [
        // PostgreSQL's DETAIL for NOT NULL and CHECK violations repeats the whole row.
        '/Failing row contains \(.*?\)(?=\.)/s' => 'Failing row contains ([redacted])',
        // DETAIL for unique and foreign-key violations repeats the key's values.
        '/(Key \(.*?\))=\(.*?\)(?= (?:already exists|is not present|conflicts with|is still referenced))/s' => '$1=([redacted])',
        // Laravel's QueryException message repeats the SQL with every bound value filled in.
        '/(SQL: ).*?(?=\)(?: at | \{| \[|"|\R|$))/s' => '$1[redacted]',
        // Password-reset links are a working sign-in until they expire.
        '#(/password/reset/)[^\s?"\'/\\\\]+#' => '$1[redacted]',
        '/[A-Za-z0-9._%+-]+(?:@|%40)[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}/' => '[email]',
    ];

    public function __construct(protected FormatterInterface $formatter) {}

    public function format(LogRecord $record): mixed
    {
        return static::redact($this->formatter->format($record));
    }

    /**
     * @param  array<LogRecord>  $records
     */
    public function formatBatch(array $records): mixed
    {
        return static::redact($this->formatter->formatBatch($records));
    }

    /**
     * Mask personal data in formatted output: a string, or the array some formatters produce.
     */
    public static function redact(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(static::redact(...), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        // A failed match must never let the original text through.
        return preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $value)
            ?? '[log entry withheld: it could not be checked for personal data]';
    }
}
