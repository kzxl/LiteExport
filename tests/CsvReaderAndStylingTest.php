<?php

declare(strict_types=1);

namespace LiteExport\Tests;

use LiteExport\Csv\{CsvExporter, CsvReader};
use LiteExport\Excel\ExcelExporter;
use LiteExport\Exporter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class CsvReaderAndStylingTest extends TestCase
{
    public function testCsvReaderWithHeaderAndBom(): void
    {
        $data = [
            ['id' => 10, 'title' => 'Sản phẩm A', 'price' => 100],
            ['id' => 20, 'title' => 'Sản phẩm B', 'price' => 200],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'test_reader_') . '.csv';
        CsvExporter::toFile($data, $tempFile, bom: true);

        // Read using CsvReader
        $rows = CsvReader::toArray($tempFile, hasHeader: true);

        $this->assertCount(2, $rows);
        $this->assertEquals(['id' => '10', 'title' => 'Sản phẩm A', 'price' => '100'], $rows[0]);
        $this->assertEquals(['id' => '20', 'title' => 'Sản phẩm B', 'price' => '200'], $rows[1]);

        // Also test Exporter::readCsv facade
        $generator = Exporter::readCsv($tempFile);
        $this->assertInstanceOf(\Generator::class, $generator);
        $first = $generator->current();
        $this->assertEquals('10', $first['id']);

        @unlink($tempFile);
    }

    public function testCsvReaderWithoutHeader(): void
    {
        $csvContent = "1,Apple,0.5\n2,Orange,0.8\n";
        $tempFile = tempnam(sys_get_temp_dir(), 'test_no_header_') . '.csv';
        file_put_contents($tempFile, $csvContent);

        $rows = CsvReader::toArray($tempFile, hasHeader: false);
        $this->assertCount(2, $rows);
        $this->assertEquals(['1', 'Apple', '0.5'], $rows[0]);
        $this->assertEquals(['2', 'Orange', '0.8'], $rows[1]);

        @unlink($tempFile);
    }

    public function testExcelHeaderStyling(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Report Header Test'],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'test_style_') . '.xlsx';
        $count = ExcelExporter::toFile($data, $tempFile, options: [
            'header_bg' => '#2563EB',
            'header_color' => '#FFFFFF',
            'header_bold' => true,
        ]);

        $this->assertSame(1, $count);
        $this->assertFileExists($tempFile);

        // Verify OpenXmlTemplate styles generation directly
        $stylesXml = \LiteExport\Excel\OpenXmlTemplate::styles('#2563EB', '#FFFFFF', true);
        $this->assertStringContainsString('rgb="FF2563EB"', $stylesXml);
        $this->assertStringContainsString('rgb="FFFFFFFF"', $stylesXml);
        $this->assertStringContainsString('<b/>', $stylesXml);
        $this->assertStringContainsString('applyFill="1"', $stylesXml);

        $defaultStyles = \LiteExport\Excel\OpenXmlTemplate::styles();
        $this->assertStringNotContainsString('rgb="FF2563EB"', $defaultStyles);

        @unlink($tempFile);
    }
}
