<?php

declare(strict_types=1);

namespace LiteExport\Tests;

use PHPUnit\Framework\TestCase;
use LiteExport\Excel\ExcelExporter;
use LiteExport\Exporter;

class ExcelExporterTest extends TestCase
{
    public function testExportsValidXlsxZipArchive(): void
    {
        $data = [
            ['id' => 101, 'product' => 'Industrial Monitor', 'price' => 250.75, 'active' => true],
            ['id' => 102, 'product' => 'Optical Sensor', 'price' => 45.00, 'active' => false],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'test_xlsx_') . '.xlsx';
        $count = ExcelExporter::toFile($data, $tempFile, sheetName: 'Products');

        $this->assertSame(2, $count);
        $this->assertFileExists($tempFile);

        // Verify valid zip header PK\x03\x04
        $content = (string) file_get_contents($tempFile);
        $this->assertStringStartsWith("PK\x03\x04", $content);

        // Verify internal OpenXML files exist in central directory
        $this->assertStringContainsString('[Content_Types].xml', $content);
        $this->assertStringContainsString('xl/worksheets/sheet1.xml', $content);
        $this->assertStringContainsString('xl/styles.xml', $content);

        @unlink($tempFile);
    }

    public function testStreamsLargeDatasetUnderMemoryCap(): void
    {
        $memBefore = memory_get_usage(true);

        $largeGenerator = function () {
            for ($i = 1; $i <= 5000; $i++) {
                yield [
                    'id' => $i,
                    'sku' => "SKU-{$i}-TEST",
                    'qty' => $i * 2,
                    'price' => 19.99,
                    'timestamp' => '2026-09-09 12:00:00',
                ];
            }
        };

        $tempFile = tempnam(sys_get_temp_dir(), 'test_large_') . '.xlsx';
        $count = Exporter::xlsx($largeGenerator(), $tempFile);

        $this->assertSame(5000, $count);
        $this->assertFileExists($tempFile);

        $memAfter = memory_get_usage(true);
        $memDeltaMb = ($memAfter - $memBefore) / (1024 * 1024);

        // Memory delta should strictly stay well below 16MB (typically < 3MB)
        $this->assertLessThan(16.0, $memDeltaMb, "Memory increased by {$memDeltaMb}MB, exceeding 16MB threshold.");

        @unlink($tempFile);
    }
}
