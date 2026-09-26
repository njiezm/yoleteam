<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Minimal single-sheet .xlsx (Office Open XML) writer: inline strings, numbers, a bold style and column widths.
 * Built on ext-zip so no spreadsheet dependency is needed.
 */
class XlsxWriter
{
    /** @var list<string> */
    private array $rows = [];

    /** @var list<float|int> */
    private array $columnWidths = [];

    public function __construct(private readonly string $sheetName = 'Feuille 1') {}

    /**
     * @param  list<float|int>  $widths  Column widths in characters, from column A.
     */
    public function setColumnWidths(array $widths): static
    {
        $this->columnWidths = array_values($widths);

        return $this;
    }

    /**
     * Appends a row; null cells are left empty, ints / floats are written as numbers, everything else as text.
     *
     * @param  array<int, mixed>  $cells
     */
    public function addRow(array $cells, bool $bold = false): static
    {
        $rowNumber = count($this->rows) + 1;
        $xml = '';

        foreach (array_values($cells) as $index => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $reference = self::columnLetter($index).$rowNumber;
            $style = $bold ? ' s="1"' : '';

            if (is_int($value) || is_float($value)) {
                $xml .= '<c r="'.$reference.'"'.$style.'><v>'.$value.'</v></c>';
            } else {
                $xml .= '<c r="'.$reference.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'.self::escape((string) $value).'</t></is></c>';
            }
        }

        $this->rows[] = '<row r="'.$rowNumber.'">'.$xml.'</row>';

        return $this;
    }

    /** Binary contents of the .xlsx file. */
    public function toString(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de créer le fichier Excel.');
        }

        foreach ($this->parts() as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        $binary = (string) file_get_contents($path);
        @unlink($path);

        return $binary;
    }

    /** "A", "B", …, "Z", "AA", … for a zero-based column index. */
    public static function columnLetter(int $index): string
    {
        $letters = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letters = chr(65 + ($index - 1) % 26).$letters;
        }

        return $letters;
    }

    /**
     * @return array<string, string>
     */
    private function parts(): array
    {
        $header = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $relationships = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        $columns = '';
        foreach ($this->columnWidths as $index => $width) {
            $columns .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        return [
            '[Content_Types].xml' => $header.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>',
            '_rels/.rels' => $header.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="'.$relationships.'/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => $header.'<workbook xmlns="'.$main.'" xmlns:r="'.$relationships.'">'
                .'<sheets><sheet name="'.self::escape(mb_substr($this->sheetName, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets>'
                .'</workbook>',
            'xl/_rels/workbook.xml.rels' => $header.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="'.$relationships.'/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="'.$relationships.'/styles" Target="styles.xml"/>'
                .'</Relationships>',
            'xl/styles.xml' => $header.'<styleSheet xmlns="'.$main.'">'
                .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
                .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
                .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
                .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
                .'</styleSheet>',
            'xl/worksheets/sheet1.xml' => $header.'<worksheet xmlns="'.$main.'" xmlns:r="'.$relationships.'">'
                .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                .($columns !== '' ? '<cols>'.$columns.'</cols>' : '')
                .'<sheetData>'.implode('', $this->rows).'</sheetData>'
                .'</worksheet>',
        ];
    }

    /** XML-escapes a value and drops the control characters XML 1.0 forbids. */
    private static function escape(string $value): string
    {
        $value = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
