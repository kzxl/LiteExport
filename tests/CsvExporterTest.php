<?php

declare(strict_types=1);

namespace LiteExport\Tests;

use PHPUnit\Framework\TestCase;
use LiteExport\Csv\CsvExporter;
use LiteExport\Exporter;

class CsvExporterTest extends TestCase
{
    public function testExportsToStringWithBom(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Nguyễn Văn A', 'score' => 9.5],
            ['id' => 2, 'name' => 'Trần Thị B', 'score' => 8.0],
        ];

        $csv = CsvExporter::toString($data);

        // Check BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('id,name,score', $csv);
        $this->assertStringContainsString('Nguyễn Văn A', $csv);
        $this->assertStringContainsString('Trần Thị B', $csv);
    }

    public function testStreamsFromGenerator(): void
    {
        $generator = function () {
            for ($i = 1; $i <= 5; $i++) {
                yield ['row' => $i, 'value' => "Item {$i}"];
            }
        };

        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv_');
        $count = Exporter::csv($generator(), $tempFile);

        $this->assertSame(5, $count);
        $this->assertFileExists($tempFile);

        $content = (string) file_get_contents($tempFile);
        $this->assertStringContainsString('row,value', $content);
        $this->assertStringContainsString('Item 5', $content);

        @unlink($tempFile);
    }

    public function testFormulaInjectionMitigation(): void
    {
        $maliciousData = [
            ['name' => '=SUM(1,2)', 'command' => '-2+3+cmd| /C calc!A0', 'normal_negative' => -150, 'text' => '@dangerous'],
        ];

        $csv = CsvExporter::toString($maliciousData);

        // Formulas starting with =, -, @ should be prefixed with single quote '
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertStringContainsString("'-2+3+cmd", $csv);
        $this->assertStringContainsString("'@dangerous", $csv);

        // Pure numeric negative numbers should NOT be escaped
        $this->assertStringContainsString("-150", $csv);
        $this->assertStringNotContainsString("'-150", $csv);
    }
}
