<?php

namespace App\Services\Import;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The example workbook offered on the import page: a "Daftar Perhiasan" sheet in the format the importer reads, with
 * three made-up rows, and a "Petunjuk" sheet explaining the columns. The example rows are fictional; they import
 * without warnings so the owner can see how a clean preview looks, then replace them with their own.
 */
class ImportTemplate
{
    public const FILENAME = 'contoh-impor-perhiasan.xlsx';

    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** @return list<string> */
    public static function header(): array
    {
        return ['No', 'Nama Perhiasan', 'Toko / Lokasi Pembelian', 'Kategori', 'Tanggal Beli', 'Kadar', 'Berat (Gram)', 'Harga Beli (Rp)', 'POTONGAN'];
    }

    /** @return list<list<string>> */
    public static function examples(): array
    {
        return [
            ['1', 'Gelang kaku contoh', 'Toko Emas Contoh', 'Gelang', '2024-03-15', '17K', '6.08', '4895000', '10%'],
            ['2', 'Cincin polos contoh', 'Toko Emas Contoh', 'Cincin', '2025-01-20', '18K', '2.5', '5100000', '15%'],
            ['3', 'Kalung rantai contoh', 'Toko Perhiasan Maju', 'Kalung', '2025-06-01', '750', '10.2', '21000000', ''],
        ];
    }

    /** @return list<list<string>> */
    public static function instructions(): array
    {
        return [
            ['PETUNJUK IMPOR PERHIASAN'],
            [''],
            ['Isi sheet "Daftar Perhiasan". Hapus tiga baris contoh dan ganti dengan data Anda. Baris judul kolom jangan diubah.'],
            [''],
            ['Kolom wajib: Nama Perhiasan, dan Berat (Gram) atau Harga Beli (Rp).'],
            ['Kolom lain boleh kosong: Toko / Lokasi Pembelian, Kategori, Tanggal Beli, Kadar, POTONGAN.'],
            [''],
            ['Kategori: Cincin, Kalung (atau Kalung / Koye), Gelang, Anting, Liontin. Yang lain dicatat sebagai "Lainnya".'],
            ['Tanggal Beli: 2024-03-15 atau 15/03/2024 (tidak boleh tanggal yang belum terjadi).'],
            ['Kadar: 17K (karat), 750 (permil), atau 75.5% (persen). Bila ditulis dua-duanya dan tidak cocok (mis. 750/17K), karat dipakai dan baris ditandai "perlu dicek".'],
            ['Berat (Gram): berat bersih logamnya, tanpa batu. Gunakan titik atau koma sebagai desimal (6.08 atau 6,08).'],
            ['Harga Beli (Rp): angka biasa, mis. 4895000 atau 4.895.000.'],
            ['POTONGAN: potongan jual dalam persen, mis. 10% atau 15. Dipakai untuk memperkirakan harga jual.'],
            [''],
            ['Kolom Harga / Gram tidak perlu; aplikasi menghitungnya. Rumus Excel tidak dijalankan: tulis angka biasa.'],
            ['Batas: berkas .xlsx maksimal 2 MB dan 500 baris per sheet. Anda akan melihat pratinjau dulu sebelum apa pun disimpan.'],
        ];
    }

    /** The workbook as bytes. */
    public function render(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        try {
            $writer = new Writer;
            $writer->getOptions()->setColumnWidth(5, 1);
            $writer->getOptions()->setColumnWidth(34, 2);
            $writer->getOptions()->setColumnWidth(28, 3);
            $writer->getOptions()->setColumnWidth(12, 4);
            $writer->getOptions()->setColumnWidth(14, 5);
            $writer->getOptions()->setColumnWidth(10, 6);
            $writer->getOptions()->setColumnWidth(14, 7);
            $writer->getOptions()->setColumnWidth(16, 8);
            $writer->getOptions()->setColumnWidth(12, 9);
            $writer->openToFile($path);

            $writer->getCurrentSheet()->setName('Daftar Perhiasan');
            $bold = (new Style)->setFontBold();
            $writer->addRow(Row::fromValues(self::header(), $bold));
            foreach (self::examples() as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->addNewSheetAndMakeItCurrent();
            $writer->getCurrentSheet()->setName('Petunjuk');
            foreach (self::instructions() as $i => $row) {
                $writer->addRow(Row::fromValues($row, $i === 0 ? $bold : null));
            }

            $writer->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}
