<?php

declare(strict_types=1);

namespace LiteExport\Csv;

use Generator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Low-memory streaming CSV reader / importer.
 * Reads CSV files line-by-line using Generators, stripping UTF-8 BOM automatically and mapping headers to assoc arrays.
 */
class CsvReader
{
    /**
     * Read CSV rows lazily using a Generator.
     *
     * @param string $filePath
     * @param bool $hasHeader If true, yields associative arrays [header => value]. If false, yields indexed arrays.
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escapeChar
     * @return Generator<int, array<string, mixed>|array<int, mixed>>
     */
    public static function read(
        string $filePath,
        bool $hasHeader = true,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escapeChar = "\\"
    ): Generator {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("File not found or unreadable: {$filePath}");
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Failed to open file: {$filePath}");
        }

        try {
            yield from self::readStream($handle, $hasHeader, $delimiter, $enclosure, $escapeChar);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Read CSV from stream resource lazily.
     *
     * @param resource $stream
     * @param bool $hasHeader
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escapeChar
     * @return Generator<int, array<string, mixed>|array<int, mixed>>
     */
    public static function readStream(
        $stream,
        bool $hasHeader = true,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escapeChar = "\\"
    ): Generator {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Expected a valid stream resource.');
        }

        $headers = null;
        $isFirst = true;

        while (($row = fgetcsv($stream, 0, $delimiter, $enclosure, $escapeChar)) !== false) {
            // Handle UTF-8 BOM on first column of first line
            if ($isFirst && !empty($row)) {
                $isFirst = false;
                if (isset($row[0]) && is_string($row[0]) && str_starts_with($row[0], "\xEF\xBB\xBF")) {
                    $row[0] = substr($row[0], 3);
                }
            }

            // Skip empty rows
            if (count($row) === 1 && ($row[0] === null || trim((string)$row[0]) === '')) {
                continue;
            }

            if ($hasHeader && $headers === null) {
                $headers = array_map('trim', $row);
                continue;
            }

            if ($hasHeader && $headers !== null) {
                $item = [];
                foreach ($headers as $index => $colName) {
                    $item[$colName] = $row[$index] ?? null;
                }
                yield $item;
            } else {
                yield $row;
            }
        }
    }

    /**
     * Read entire CSV file into an array.
     *
     * @return list<array<string, mixed>|array<int, mixed>>
     */
    public static function toArray(
        string $filePath,
        bool $hasHeader = true,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escapeChar = "\\"
    ): array {
        return iterator_to_array(self::read($filePath, $hasHeader, $delimiter, $enclosure, $escapeChar), false);
    }
}
