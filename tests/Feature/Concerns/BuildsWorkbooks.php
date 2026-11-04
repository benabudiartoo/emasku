<?php

namespace Tests\Feature\Concerns;

use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Builds small .xlsx workbooks inside a test, shaped like the owner's "Rekap perhiasan" file. */
trait BuildsWorkbooks
{
    /** @var list<string> */
    private array $workbookFiles = [];

    protected function headerRow(): array
    {
        return ['No', 'Nama Perhiasan', 'Toko / Lokasi Pembelian', 'Kategori', 'Tanggal Beli', 'Kadar', 'Berat (Gram)', 'Harga Beli (Rp)', 'Harga / Gram (Rp)'];
    }

    /** A data row: No, name, store, category, date, purity, weight, cost, and the Harga / Gram formula the app ignores. */
    protected function jewelryRow(int $no, string $name, string $store = 'Toko Emas Mulia', string $category = 'Gelang', string|\DateTimeInterface $date = '2023-04-24', string $purity = '17K', string|float $weight = '6.08', string|int $cost = '4895000'): array
    {
        return [(string) $no, $name, $store, $category, $date, $purity, $weight, $cost, '=H'.($no + 3).'/G'.($no + 3)];
    }

    /**
     * @param  array<string, list<list<mixed>>>  $sheets  sheet name => rows
     * @return string path of the file
     */
    protected function workbook(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wb').'.xlsx';
        $this->workbookFiles[] = $path;

        $writer = new Writer;
        $writer->openToFile($path);
        $first = true;
        foreach ($sheets as $name => $rows) {
            if (! $first) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $first = false;
            $writer->getCurrentSheet()->setName((string) $name);
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        }
        $writer->close();

        return $path;
    }

    /** A workbook with one "Daftar Perhiasan" sheet: title lines, the header, then $rows. */
    protected function standardWorkbook(array $rows, string $sheet = 'Daftar Perhiasan'): string
    {
        return $this->workbook([$sheet => [['DAFTAR DETAIL PERHIASAN'], ['Rincian Transaksi'], $this->headerRow(), ...$rows]]);
    }

    protected function upload(string $path, string $name = 'Rekap perhiasan.xlsx'): UploadedFile
    {
        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    protected function cleanWorkbooks(): void
    {
        foreach ($this->workbookFiles as $file) {
            @unlink($file);
        }
        $this->workbookFiles = [];
    }
}
