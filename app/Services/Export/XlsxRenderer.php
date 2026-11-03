<?php

namespace App\Services\Export;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Draws a Report as one .xlsx sheet. Every value is written as text, so a weight such as 12.3456 is never turned
 * into a float, and text that starts with a formula character is neutralized so it is never evaluated.
 */
class XlsxRenderer
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** Characters a spreadsheet reads as the start of a formula. */
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    public function render(Report $report): string
    {
        $lines = $this->lines($report);

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        try {
            $writer = new Writer;
            foreach ($this->widths($lines) as $column => $width) {
                $writer->getOptions()->setColumnWidth($width, $column + 1);
            }
            $writer->openToFile($path);

            $bold = (new Style)->setFontBold();
            foreach ($lines as [$cells, $isBold]) {
                $writer->addRow(Row::fromValues($cells, $isBold ? $bold : null));
            }
            $writer->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /** Text that starts with a formula character gets a leading apostrophe so it stays text. */
    public static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], self::FORMULA_STARTS, true) ? "'".$value : $value;
    }

    /** @return list<array{0: list<string>, 1: bool}> rows of text cells, and whether the row is bold */
    private function lines(Report $report): array
    {
        $lines = [[[$report->title], true], [[''], false]];

        foreach ($report->facts as $label => $value) {
            $lines[] = [[$label, $value], false];
        }
        if ($report->facts !== []) {
            $lines[] = [[''], false];
        }

        foreach ($report->sections as $section) {
            $lines[] = [[$section->heading], true];
            if ($section->rows === []) {
                $lines[] = [[$section->emptyText ?? 'Tidak ada data.'], false];
            } else {
                $lines[] = [$section->columns, true];
                foreach ($section->rows as $row) {
                    $lines[] = [$row, false];
                }
            }
            $lines[] = [[''], false];
        }

        foreach ($report->notes as $note) {
            $lines[] = [[$note], false];
        }

        return array_map(fn (array $line) => [array_map(fn ($v) => self::safe((string) $v), $line[0]), $line[1]], $lines);
    }

    /**
     * Column widths from the table rows only (titles and notes may overflow into empty neighbours).
     *
     * @param  list<array{0: list<string>, 1: bool}>  $lines
     * @return list<int>
     */
    private function widths(array $lines): array
    {
        $widths = [];
        foreach ($lines as [$cells]) {
            if (count($cells) < 2) {
                continue;
            }
            foreach ($cells as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 10, min(50, mb_strlen($cell) + 2));
            }
        }

        return $widths;
    }
}
