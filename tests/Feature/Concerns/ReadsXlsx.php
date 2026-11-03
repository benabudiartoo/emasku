<?php

namespace Tests\Feature\Concerns;

use OpenSpout\Reader\XLSX\Reader;

/** Reads an .xlsx given as bytes back into rows of text, the way a spreadsheet program would see it. */
trait ReadsXlsx
{
    /** @return list<list<string>> */
    protected function readXlsx(string $bytes): array
    {
        $this->assertSame('PK', substr($bytes, 0, 2), 'not a zip/xlsx file');

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $bytes);

        $rows = [];
        $reader = new Reader;
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn ($cell) => (string) $cell->getValue(), $row->getCells());
                }
            }
            $reader->close();
        } finally {
            @unlink($path);
        }

        return $rows;
    }

    /** True when some row of the sheet contains a cell with exactly this text. */
    protected function sheetHas(array $rows, string $text): bool
    {
        foreach ($rows as $row) {
            if (in_array($text, $row, true)) {
                return true;
            }
        }

        return false;
    }
}
