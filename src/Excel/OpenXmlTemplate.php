<?php

declare(strict_types=1);

namespace LiteExport\Excel;

final class OpenXmlTemplate
{
    public static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    public static function packageRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    public static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    public static function workbook(string $sheetName = 'Sheet1'): string
    {
        $escapedName = htmlspecialchars($sheetName, ENT_XML1, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="' . $escapedName . '" sheetId="1" r:id="rId1"/>'
            . '</sheets>'
            . '</workbook>';
    }

    public static function styles(?string $headerBg = null, ?string $headerColor = null, bool $headerBold = true): string
    {
        $boldTag = $headerBold ? '<b/>' : '';
        $colorTag = '';
        if ($headerColor !== null && $headerColor !== '') {
            $hex = strtoupper(ltrim($headerColor, '#'));
            if (strlen($hex) === 6) {
                $hex = 'FF' . $hex;
            }
            $colorTag = "<color rgb=\"{$hex}\"/>";
        }

        $fillsXml = '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>';
        $headerFillId = 0;
        $applyFillAttr = '';

        if ($headerBg !== null && $headerBg !== '') {
            $bgHex = strtoupper(ltrim($headerBg, '#'));
            if (strlen($bgHex) === 6) {
                $bgHex = 'FF' . $bgHex;
            }
            $fillsXml = '<fills count="3">'
                . '<fill><patternFill patternType="none"/></fill>'
                . '<fill><patternFill patternType="gray125"/></fill>'
                . '<fill><patternFill patternType="solid"><fgColor rgb="' . $bgHex . '"/><bgColor indexed="64"/></patternFill></fill>'
                . '</fills>';
            $headerFillId = 2;
            $applyFillAttr = ' applyFill="1"';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font>' . $boldTag . $colorTag . '<sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . $fillsXml
            . '<borders count="1"><border><left/><right/><top/><bottom/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="' . $headerFillId . '" borderId="0" xfId="0" applyFont="1"' . $applyFillAttr . '/>'
            . '</cellXfs>'
            . '</styleSheet>';
    }
}
