<?php

declare(strict_types=1);

namespace LiteExport;

use LiteExport\Csv\CsvExporter;
use LiteExport\Excel\ExcelExporter;

/**
 * Universal Data Export Façade.
 */
class Exporter
{
    /**
     * Export rows to CSV.
     * If $filePath is provided, streams to file and returns row count.
     * If $filePath is null, returns the full CSV string.
     *
     * @param iterable<int|string, array<string, mixed>|object> $rows
     */
    public static function csv(
        iterable $rows,
        ?string $filePath = null,
        ?array $headers = null,
        string $delimiter = ',',
        bool $bom = true,
    ): string|int {
        if ($filePath !== null) {
            return CsvExporter::toFile($rows, $filePath, $headers, $delimiter, bom: $bom);
        }

        return CsvExporter::toString($rows, $headers, $delimiter, bom: $bom);
    }

    /**
     * Export rows to Excel (.xlsx).
     * If $filePath is provided, streams to file and returns row count.
     * If $filePath is null, returns the raw .xlsx binary string.
     *
     * @param iterable<int|string, array<string, mixed>|object> $rows
     */
    public static function xlsx(
        iterable $rows,
        ?string $filePath = null,
        ?array $headers = null,
        string $sheetName = 'Sheet1',
    ): string|int {
        if ($filePath !== null) {
            return ExcelExporter::toFile($rows, $filePath, $headers, $sheetName);
        }

        return ExcelExporter::toString($rows, $headers, $sheetName);
    }
}
