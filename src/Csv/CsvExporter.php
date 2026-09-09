<?php

declare(strict_types=1);

namespace LiteExport\Csv;

/**
 * High-performance streaming CSV exporter.
 * Streams rows with constant O(1) memory (< 5MB) and optional UTF-8 BOM for Excel compatibility.
 */
class CsvExporter
{
    /**
     * Export rows to a file.
     *
     * @param iterable<int|string, array<string, mixed>|object> $rows
     * @param string $filePath Destination file path
     * @param string[]|null $headers Header labels (if null, inferred from first row keys)
     * @param string $delimiter CSV delimiter (default: ',')
     * @param string $enclosure CSV enclosure (default: '"')
     * @param string $escapeChar CSV escape char (default: "\\")
     * @param bool $bom Prepend UTF-8 BOM for Excel compatibility
     * @return int Total rows exported
     */
    public static function toFile(
        iterable $rows,
        string $filePath,
        ?array $headers = null,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escapeChar = "\\",
        bool $bom = true,
    ): int {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($filePath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open file for writing: {$filePath}");
        }

        try {
            return self::toStream($rows, $handle, $headers, $delimiter, $enclosure, $escapeChar, $bom);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Export rows to a string.
     */
    public static function toString(
        iterable $rows,
        ?array $headers = null,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escapeChar = "\\",
        bool $bom = true,
    ): string {
        $handle = fopen('php://temp', 'r+b');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open memory buffer for writing");
        }

        try {
            self::toStream($rows, $handle, $headers, $delimiter, $enclosure, $escapeChar, $bom);
            rewind($handle);
            return (string) stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Stream rows directly into a PHP stream resource.
     *
     * @param iterable<int|string, array<string, mixed>|object> $rows
     * @param resource $stream
     */
    public static function toStream(
        iterable $rows,
        $stream,
        ?array $headers = null,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escapeChar = "\\",
        bool $bom = true,
    ): int {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Expected a valid stream resource.');
        }

        if ($bom) {
            fwrite($stream, "\xEF\xBB\xBF");
        }

        $headerWritten = false;
        $rowCount = 0;

        foreach ($rows as $row) {
            $rowArray = self::rowToArray($row);

            if (!$headerWritten) {
                $actualHeaders = $headers ?? array_keys($rowArray);
                fputcsv($stream, $actualHeaders, $delimiter, $enclosure, $escapeChar);
                $headerWritten = true;
            }

            fputcsv($stream, array_values($rowArray), $delimiter, $enclosure, $escapeChar);
            $rowCount++;
        }

        // If no rows were provided but headers were specified, still write the header
        if (!$headerWritten && $headers !== null) {
            fputcsv($stream, $headers, $delimiter, $enclosure, $escapeChar);
        }

        return $rowCount;
    }

    /**
     * @param array<string, mixed>|object $row
     * @return array<string, mixed>
     */
    private static function rowToArray(array|object $row): array
    {
        if (is_array($row)) {
            return $row;
        }

        if (method_exists($row, 'toArray')) {
            return $row->toArray();
        }

        return get_object_vars($row);
    }
}
