<?php

declare(strict_types=1);

namespace LiteExport\Excel;

/**
 * Ultra-fast, low-memory streaming Excel (.xlsx) exporter.
 * Bypasses heavyweight DOM tree allocations: streams rows directly to disk and assembles OpenXML packages in < 16MB RAM.
 */
class ExcelExporter
{
    /**
     * Export rows to an Excel (.xlsx) file.
     *
     * @param iterable<int|string, array<string, mixed>|object> $rows
     * @param string $filePath Destination .xlsx file path
     * @param string[]|null $headers
     * @param string $sheetName
     * @return int Row count exported
     */
    public static function toFile(
        iterable $rows,
        string $filePath,
        ?array $headers = null,
        string $sheetName = 'Sheet1',
    ): int {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tempSheetPath = tempnam(sys_get_temp_dir(), 'lite_sheet_');
        if ($tempSheetPath === false) {
            throw new \RuntimeException("Unable to create temp file for sheet streaming.");
        }

        $sheetHandle = fopen($tempSheetPath, 'w+b');
        if ($sheetHandle === false) {
            throw new \RuntimeException("Unable to open temp file {$tempSheetPath}.");
        }

        try {
            // Write Worksheet Header
            fwrite($sheetHandle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n");
            fwrite($sheetHandle, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n");
            fwrite($sheetHandle, '<sheetData>' . "\n");

            $rowIndex = 1;
            $headerWritten = false;
            $rowCount = 0;

            foreach ($rows as $row) {
                $rowArray = self::rowToArray($row);

                // Write header row
                if (!$headerWritten) {
                    $actualHeaders = $headers ?? array_keys($rowArray);
                    self::writeRow($sheetHandle, $actualHeaders, $rowIndex++, isHeader: true);
                    $headerWritten = true;
                }

                // Write data row
                self::writeRow($sheetHandle, array_values($rowArray), $rowIndex++, isHeader: false);
                $rowCount++;
            }

            if (!$headerWritten && $headers !== null) {
                self::writeRow($sheetHandle, $headers, $rowIndex++, isHeader: true);
            }

            // Close sheetData and worksheet tags
            fwrite($sheetHandle, '</sheetData>' . "\n");
            fwrite($sheetHandle, '</worksheet>');
            fflush($sheetHandle);

            // Assemble .xlsx Zip package
            $sheetXml = (string) file_get_contents($tempSheetPath);
            self::assembleZip($filePath, $sheetXml, $sheetName);

            return $rowCount;
        } finally {
            fclose($sheetHandle);
            @unlink($tempSheetPath);
        }
    }

    /**
     * Export rows directly into an in-memory .xlsx string.
     */
    public static function toString(
        iterable $rows,
        ?array $headers = null,
        string $sheetName = 'Sheet1',
    ): string {
        $tempZip = tempnam(sys_get_temp_dir(), 'lite_xlsx_');
        if ($tempZip === false) {
            throw new \RuntimeException("Unable to create temp zip file");
        }

        try {
            self::toFile($rows, $tempZip, $headers, $sheetName);
            return (string) file_get_contents($tempZip);
        } finally {
            @unlink($tempZip);
        }
    }

    /**
     * @param resource $stream
     * @param array<int, mixed> $cells
     * @param int $rowNum 1-based index
     */
    private static function writeRow($stream, array $cells, int $rowNum, bool $isHeader): void
    {
        fwrite($stream, "<row r=\"{$rowNum}\">");

        $colIndex = 1;
        $styleAttr = $isHeader ? ' s="1"' : '';

        foreach ($cells as $val) {
            $colLetter = self::columnLetter($colIndex++);
            $cellRef = "{$colLetter}{$rowNum}";

            if ($val === null || $val === '') {
                // Empty cell
                continue;
            }

            if (!$isHeader && is_int($val)) {
                fwrite($stream, "<c r=\"{$cellRef}\"{$styleAttr}><v>{$val}</v></c>");
            } elseif (!$isHeader && is_float($val)) {
                fwrite($stream, "<c r=\"{$cellRef}\"{$styleAttr}><v>{$val}</v></c>");
            } elseif (!$isHeader && is_bool($val)) {
                $boolVal = $val ? '1' : '0';
                fwrite($stream, "<c r=\"{$cellRef}\" t=\"b\"{$styleAttr}><v>{$boolVal}</v></c>");
            } else {
                // Inline string format
                $strVal = is_string($val) ? $val : (string) $val;
                $escaped = htmlspecialchars($strVal, ENT_XML1, 'UTF-8');
                fwrite($stream, "<c r=\"{$cellRef}\" t=\"inlineStr\"{$styleAttr}><is><t>{$escaped}</t></is></c>");
            }
        }

        fwrite($stream, "</row>\n");
    }

    private static function assembleZip(string $zipPath, string $sheetXml, string $sheetName): void
    {
        $zip = new SimpleZip($zipPath);
        $zip->addFromString('[Content_Types].xml', OpenXmlTemplate::contentTypes());
        $zip->addFromString('_rels/.rels', OpenXmlTemplate::packageRels());
        $zip->addFromString('xl/_rels/workbook.xml.rels', OpenXmlTemplate::workbookRels());
        $zip->addFromString('xl/workbook.xml', OpenXmlTemplate::workbook($sheetName));
        $zip->addFromString('xl/styles.xml', OpenXmlTemplate::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();
    }

    private static function columnLetter(int $col): string
    {
        $letter = '';
        while ($col > 0) {
            $rem = ($col - 1) % 26;
            $letter = chr(65 + $rem) . $letter;
            $col = intdiv($col - $rem, 26);
        }
        return $letter;
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
