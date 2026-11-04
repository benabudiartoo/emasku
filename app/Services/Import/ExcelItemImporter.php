<?php

namespace App\Services\Import;

use App\Models\Item;
use App\Services\GoldCalculator;
use DateTimeInterface;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Reads jewelry rows out of an .xlsx workbook, for a preview. Cells are read as text only: a formula is never
 * evaluated (a cell starting with "=" counts as empty) and the Harga / Gram column is ignored because the app works it
 * out. Nothing here touches the database except reading the owner's existing items to spot duplicates.
 *
 * Row status: ok, check (importable but worth a look: a note says why), duplicate (same name, date, weight and price
 * as an existing item or an earlier row), error (cannot be imported).
 */
class ExcelItemImporter
{
    public const MAX_ROWS = 500;

    /** Physical rows read per sheet before giving up, so a huge sheet cannot be used to waste memory. */
    public const MAX_SCAN_ROWS = 5000;

    public const MAX_SHEETS = 20;

    /** Header text (letters and digits only, lower case) -> field. */
    private const HEADERS = [
        'name' => ['namaperhiasan', 'nama', 'namabarang'],
        'store' => ['tokolokasipembelian', 'tokotempatpembelian', 'toko', 'tempatpembelian'],
        'category' => ['kategori', 'jenisperhiasan', 'jenis'],
        'date' => ['tanggalbeli', 'tanggalpembelian', 'dimilikisejak', 'tanggal'],
        'purity' => ['kadar'],
        'weight' => ['beratgram', 'beratbersih', 'berat'],
        'cost' => ['hargabelirp', 'hargabeli', 'hargaperolehan'],
        'discount' => ['potongan', 'potonganjual'],
    ];

    /** Words in a category -> jewelry type. */
    private const CATEGORY_WORDS = [
        'kalung' => 'kalung', 'koye' => 'kalung', 'gelang' => 'gelang', 'bangle' => 'gelang', 'bracelet' => 'gelang',
        'cincin' => 'cincin', 'ring' => 'cincin', 'anting' => 'anting', 'giwang' => 'anting', 'earring' => 'anting',
        'liontin' => 'liontin', 'pendant' => 'liontin',
    ];

    public function __construct(private GoldCalculator $calc) {}

    /**
     * @return array{sheets: array<string, list<array<string, mixed>>>, default: ?string}
     *
     * @throws ImportException
     */
    public function read(string $path, string $ownerId): array
    {
        $existing = $this->existingKeys($ownerId);

        $sheets = [];
        $reader = new Reader;
        try {
            $reader->open($path);
            $count = 0;
            foreach ($reader->getSheetIterator() as $sheet) {
                if (++$count > self::MAX_SHEETS) {
                    break;
                }
                $rows = $this->sheetRows($sheet);
                $parsed = $this->parseSheet($rows, $sheet->getName(), $existing);
                if ($parsed !== null) {
                    $sheets[$sheet->getName()] = $parsed;
                }
            }
        } catch (ImportException $e) {
            throw $e;
        } catch (Throwable) {
            throw new ImportException('Berkas tidak bisa dibaca. Pastikan ini berkas Excel (.xlsx) yang tidak rusak.');
        } finally {
            try {
                $reader->close();
            } catch (Throwable) {
            }
        }

        if ($sheets === []) {
            throw new ImportException('Tidak ada sheet yang dikenali. Sheet harus punya judul kolom "Nama Perhiasan" dan "Berat (Gram)" atau "Harga Beli (Rp)".');
        }

        $default = array_key_exists('Daftar Perhiasan', $sheets) ? 'Daftar Perhiasan' : array_key_first($sheets);

        return ['sheets' => $sheets, 'default' => $default];
    }

    /** @return list<list<string>> */
    private function sheetRows($sheet): array
    {
        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            if (count($rows) >= self::MAX_SCAN_ROWS) {
                throw new ImportException('Sheet "'.$sheet->getName().'" terlalu besar untuk diimpor.');
            }
            $rows[] = array_map(fn ($cell) => self::text($cell->getValue()), $row->getCells());
        }

        return $rows;
    }

    /** A cell value as text. Dates become Y-m-d; whole numbers stay whole; formulas are not evaluated. */
    private static function text(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }
        if (is_bool($value) || $value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private static function key(string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($text)) ?? '';
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  array<string, true>  $existing
     * @return ?list<array<string, mixed>> null when the sheet has no recognizable header
     *
     * @throws ImportException
     */
    private function parseSheet(array $rows, string $sheetName, array $existing): ?array
    {
        [$headerAt, $cols] = $this->findHeader($rows);
        if ($headerAt === null) {
            return null;
        }

        $headerWords = array_merge(...array_values(self::HEADERS));
        $parsed = [];
        $seen = [];

        foreach (array_slice($rows, $headerAt + 1, null, true) as $index => $raw) {
            $name = $this->cell($raw, $cols, 'name');
            if ($name === '' || in_array(self::key($name), $headerWords, true) || str_starts_with(strtolower($name), 'total')) {
                continue; // a blank line, a repeated header, or a Total line
            }
            if (count($parsed) >= self::MAX_ROWS) {
                throw new ImportException('Sheet "'.$sheetName.'" punya lebih dari '.self::MAX_ROWS.' baris. Bagi berkas menjadi beberapa bagian.');
            }

            $row = $this->mapRow($raw, $cols, $index + 1);

            // Duplicates: against the owner's existing items, then against earlier rows of this sheet.
            if ($row['status'] !== 'error') {
                $key = self::duplicateKey($row['name'], $row['owned_since'], $row['net_weight_g'], $row['acquisition_cost']);
                if (isset($existing[$key])) {
                    $row['status'] = 'duplicate';
                    $row['notes'][] = 'Barang yang sama sudah ada di koleksi Anda.';
                } elseif (isset($seen[$key])) {
                    $row['status'] = 'duplicate';
                    $row['notes'][] = 'Sama dengan baris '.$seen[$key].' di sheet ini.';
                } else {
                    $seen[$key] = $row['row'];
                }
            }

            $row['selected'] = in_array($row['status'], ['ok', 'check'], true);
            $parsed[] = $row;
        }

        return $parsed;
    }

    /** @return array{0: ?int, 1: array<string, int>} the header row and the column of each recognized field */
    private function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 20, true) as $index => $raw) {
            $cols = [];
            foreach ($raw as $i => $cell) {
                $k = self::key($cell);
                foreach (self::HEADERS as $field => $aliases) {
                    if (! isset($cols[$field]) && in_array($k, $aliases, true)) {
                        $cols[$field] = $i;
                    }
                }
            }
            if (isset($cols['name']) && (isset($cols['weight']) || isset($cols['cost']))) {
                return [$index, $cols];
            }
        }

        return [null, []];
    }

    private function cell(array $raw, array $cols, string $field): string
    {
        $value = isset($cols[$field]) ? ($raw[$cols[$field]] ?? '') : '';

        return str_starts_with($value, '=') ? '' : $value; // a formula is never read as a value
    }

    /** @return array<string, mixed> */
    private function mapRow(array $raw, array $cols, int $rowNumber): array
    {
        $notes = [];
        $check = false;
        $error = false;

        $name = $this->cell($raw, $cols, 'name');
        if (mb_strlen($name) > 160) {
            $error = true;
            $notes[] = 'Nama lebih dari 160 karakter.';
        }

        $store = $this->cell($raw, $cols, 'store');
        if (mb_strlen($store) > 120) {
            $store = mb_substr($store, 0, 120);
            $check = true;
            $notes[] = 'Nama toko dipotong menjadi 120 karakter.';
        }

        $categoryText = $this->cell($raw, $cols, 'category');
        $type = null;
        if ($categoryText !== '') {
            $type = 'lainnya';
            $k = self::key($categoryText);
            foreach (self::CATEGORY_WORDS as $word => $mapped) {
                if (str_contains($k, $word)) {
                    $type = $mapped;
                    break;
                }
            }
            if ($type === 'lainnya') {
                $check = true;
                $notes[] = 'Kategori "'.$categoryText.'" tidak dikenal, dipakai "Lainnya".';
            }
        }

        $dateText = $this->cell($raw, $cols, 'date');
        $date = $dateText === '' ? null : self::date($dateText);
        if ($dateText !== '' && $date === null) {
            $check = true;
            $notes[] = 'Tanggal "'.$dateText.'" tidak terbaca atau di masa depan; dikosongkan.';
        }

        $purityText = $this->cell($raw, $cols, 'purity');
        $purity = PurityParser::parse($purityText);
        if ($purity['conflict']) {
            $check = true;
            $notes[] = 'Kadar "'.$purityText.'": angka karat dan permil tidak cocok. Dipakai karat ('.$purity['input'].'K).';
        } elseif ($purity['unreadable']) {
            $check = true;
            $notes[] = 'Kadar "'.$purityText.'" tidak terbaca; diimpor tanpa kadar.';
        } elseif ($purityText === '') {
            $check = true;
            $notes[] = 'Kadar kosong; diimpor tanpa kadar.';
        }

        $weightText = $this->cell($raw, $cols, 'weight');
        $weight = self::decimal($weightText);
        if ($weight === null || bccomp($weight, '0', 4) <= 0 || bccomp($weight, '1000000', 4) > 0) {
            $notes[] = $weightText === '' ? 'Berat kosong.' : 'Berat "'.$weightText.'" tidak valid; dikosongkan.';
            $weight = null;
            $check = true;
        }

        $costText = $this->cell($raw, $cols, 'cost');
        $cost = self::money($costText);
        if ($cost === null || bccomp($cost, '0', 2) < 0 || bccomp($cost, '9999999999999999', 2) > 0) {
            if ($costText !== '') {
                $check = true;
                $notes[] = 'Harga beli "'.$costText.'" tidak valid; dikosongkan.';
            }
            $cost = null;
        }

        $discountText = $this->cell($raw, $cols, 'discount');
        $discount = $discountText === '' ? null : self::discount($discountText);
        if ($discountText !== '' && $discount === null) {
            $check = true;
            $notes[] = 'Potongan "'.$discountText.'" tidak valid (harus 0 sampai 100); dikosongkan.';
        }

        return [
            'row' => $rowNumber,
            'name' => $name,
            'store' => $store === '' ? null : $store,
            'jewelry_type' => $type,
            'owned_since' => $date,
            'purity_source' => $purity['source'],
            'purity_input' => $purity['input'],
            'purity_fraction' => $purity['fraction'],
            'purity_text' => $purityText,
            'net_weight_g' => $weight,
            'acquisition_cost' => $cost,
            'resale_discount_percent' => $discount,
            'status' => $error ? 'error' : ($check ? 'check' : 'ok'),
            'notes' => $notes,
            'selected' => false,
        ];
    }

    // ---- parsing helpers (public for tests) -------------------------------------------------------------------

    /** Y-m-d from text (2023-04-24, 24/04/2023, 24-04-2023) or an Excel date serial; null if unreadable or in the future. */
    public static function date(string $text): ?string
    {
        $text = trim($text);
        $date = null;

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $text, $m)) {
            $date = checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
        } elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $text, $m)) {
            $date = checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;
        } elseif (preg_match('/^\d{5}(\.\d+)?$/', $text)) {
            // Excel serial day number (days since 1899-12-30).
            $date = date('Y-m-d', (int) ((((int) $text) - 25569) * 86400));
        }

        return $date !== null && $date <= date('Y-m-d') && $date >= '1900-01-01' ? $date : null;
    }

    /** A weight: "." or "," is always the decimal point. Null if it is not a plain number. */
    public static function decimal(string $text): ?string
    {
        $text = str_replace(',', '.', trim(str_replace("\u{00A0}", '', $text)));

        return preg_match('/^\d+(\.\d+)?$/', $text) === 1 ? $text : null;
    }

    /** An amount of money: "Rp", spaces and thousands separators are understood (4.895.000, 4,895,000, 4895000). */
    public static function money(string $text): ?string
    {
        $text = trim(str_ireplace(['Rp', ' ', "\u{00A0}"], '', $text));
        if ($text === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $text)) {
            return $text;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $text)) {
            return str_replace(['.', ','], ['', '.'], $text); // 4.895.000 or 4.895.000,50
        }
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $text)) {
            return str_replace(',', '', $text); // 4,895,000 or 4,895,000.50
        }
        if (preg_match('/^\d+[.,]\d{1,2}$/', $text)) {
            return str_replace(',', '.', $text); // 4895000.5
        }

        return null;
    }

    /** A discount in percent, from "15%", "15", or a spreadsheet percentage stored as a fraction (0.15). Null if not 0 to 100. */
    public static function discount(string $text): ?string
    {
        $text = str_replace(',', '.', trim($text));
        $isPercent = str_ends_with($text, '%');
        $number = rtrim($text, '% ');

        if (preg_match('/^\d+(\.\d+)?$/', $number) !== 1) {
            return null;
        }
        // A bare number up to 1 is how a spreadsheet stores a percentage (0.15 = 15 percent).
        $percent = (! $isPercent && bccomp($number, '1', 6) <= 0) ? bcmul($number, '100', 6) : $number;

        return bccomp($percent, '100', 6) > 0 ? null : GoldCalculator::round($percent, 2);
    }

    /** The key two items share when they are "the same": name, date, net weight, and price. */
    public static function duplicateKey(string $name, ?string $date, ?string $net, ?string $cost): string
    {
        return implode('|', [
            mb_strtolower(trim($name)),
            $date ?? '',
            $net === null ? '' : GoldCalculator::round(bcadd($net, '0', 10), 4),
            $cost === null ? '' : GoldCalculator::round(bcadd($cost, '0', 10), 2),
        ]);
    }

    /** @return array<string, true> */
    private function existingKeys(string $ownerId): array
    {
        $keys = [];
        foreach (Item::where('user_id', $ownerId)->with('units')->get() as $item) {
            $net = $this->calc->netWeight($item);
            $keys[self::duplicateKey(
                $item->name,
                $item->owned_since?->format('Y-m-d'),
                $net->isComplete() ? $net->value : null,
                $item->acquisition_cost === null ? null : (string) $item->acquisition_cost,
            )] = true;
        }

        return $keys;
    }
}
