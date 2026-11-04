<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Services\Import\ExcelItemImporter;
use App\Services\Import\ImportException;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsWorkbooks;
use Tests\Feature\Concerns\CreatesZakatCollections;
use Tests\TestCase;

class ExcelItemImporterTest extends TestCase
{
    use BuildsWorkbooks, CreatesZakatCollections, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->cleanWorkbooks();
        parent::tearDown();
    }

    /** @return list<array<string, mixed>> the rows of the default sheet */
    private function rows(string $path): array
    {
        $result = app(ExcelItemImporter::class)->read($path, $this->user->id);

        return $result['sheets'][$result['default']];
    }

    public function test_a_standard_row_is_mapped_field_by_field(): void
    {
        $rows = $this->rows($this->standardWorkbook([$this->jewelryRow(1, 'Gelang kaku putih', 'Toko Emas Mulia Gold Palembang', 'Gelang', '2023-04-24', '17K', '6.08', '4895000')]));

        $this->assertCount(1, $rows);
        $r = $rows[0];
        $this->assertSame('Gelang kaku putih', $r['name']);
        $this->assertSame('Toko Emas Mulia Gold Palembang', $r['store']);
        $this->assertSame('gelang', $r['jewelry_type']);
        $this->assertSame('2023-04-24', $r['owned_since']);
        $this->assertSame('karat', $r['purity_source']);
        $this->assertSame('17', $r['purity_input']);
        $this->assertSame('0.708333', $r['purity_fraction']);
        $this->assertSame('6.08', $r['net_weight_g']);
        $this->assertSame('4895000', $r['acquisition_cost']);
        $this->assertNull($r['resale_discount_percent']);
        $this->assertSame('ok', $r['status']);
        $this->assertTrue($r['selected']);
        $this->assertSame(4, $r['row']); // the Excel row: two title lines, the header, then the first data row
    }

    public function test_numeric_cells_keep_their_exact_decimals(): void
    {
        // A real Excel number (a float), not text: 6.08 must not turn into 6.0800000001.
        $rows = $this->rows($this->standardWorkbook([$this->jewelryRow(1, 'A', weight: 6.08, cost: 4895000)]));

        $this->assertSame('6.08', $rows[0]['net_weight_g']);
        $this->assertSame('4895000', $rows[0]['acquisition_cost']);
    }

    public function test_categories_map_to_jewelry_types_and_unknown_ones_are_flagged(): void
    {
        $map = ['Gelang' => 'gelang', 'Cincin' => 'cincin', 'Kalung / Koye' => 'kalung', 'Liontin' => 'liontin', 'Anting' => 'anting', 'Giwang' => 'anting', 'BANGLE' => 'gelang'];
        $data = [];
        $n = 0;
        foreach (array_keys($map) as $category) {
            $data[] = $this->jewelryRow(++$n, "Barang {$n}", category: $category, date: "2024-01-0{$n}");
        }
        $rows = $this->rows($this->standardWorkbook($data));

        $this->assertSame(array_values($map), array_column($rows, 'jewelry_type'));
        $this->assertSame(['ok'], array_unique(array_column($rows, 'status')));

        $odd = $this->rows($this->standardWorkbook([$this->jewelryRow(1, 'Bros', category: 'Bros')]));
        $this->assertSame('lainnya', $odd[0]['jewelry_type']);
        $this->assertSame('check', $odd[0]['status']);
        $this->assertStringContainsString('Bros', $odd[0]['notes'][0]);

        $none = $this->rows($this->standardWorkbook([$this->jewelryRow(1, 'Tanpa kategori', category: '')]));
        $this->assertNull($none[0]['jewelry_type']);
        $this->assertSame('ok', $none[0]['status']);
    }

    public function test_dates_are_read_from_text_dd_mm_yyyy_excel_serials_and_date_cells(): void
    {
        $rows = $this->rows($this->standardWorkbook([
            $this->jewelryRow(1, 'Teks', date: '2023-04-24'),
            $this->jewelryRow(2, 'Hari-bulan', date: '24/04/2023'),
            $this->jewelryRow(3, 'Serial', date: '45000'), // Excel serial: 2023-03-15
            $this->jewelryRow(4, 'Sel tanggal', date: new DateTimeImmutable('2022-12-31')),
        ]));

        $this->assertSame(['2023-04-24', '2023-04-24', '2023-03-15', '2022-12-31'], array_column($rows, 'owned_since'));
    }

    public function test_an_unreadable_or_future_date_is_emptied_and_flagged(): void
    {
        $rows = $this->rows($this->standardWorkbook([
            $this->jewelryRow(1, 'Salah', date: '31/02/2023'),
            $this->jewelryRow(2, 'Masa depan', date: now()->addYear()->toDateString()),
            $this->jewelryRow(3, 'Kosong', date: ''),
        ]));

        $this->assertNull($rows[0]['owned_since']);
        $this->assertSame('check', $rows[0]['status']);
        $this->assertNull($rows[1]['owned_since']);
        $this->assertSame('check', $rows[1]['status']);
        $this->assertNull($rows[2]['owned_since']);
        $this->assertSame('ok', $rows[2]['status']); // an empty date is simply not filled in
    }

    public function test_helpers_read_money_weights_discounts_and_dates(): void
    {
        $this->assertSame('4895000', ExcelItemImporter::money('Rp 4.895.000'));
        $this->assertSame('4895000', ExcelItemImporter::money('4,895,000'));
        $this->assertSame('4895000.50', ExcelItemImporter::money('4.895.000,50'));
        $this->assertSame('4895000', ExcelItemImporter::money('4895000'));
        $this->assertNull(ExcelItemImporter::money('abc'));
        $this->assertNull(ExcelItemImporter::money('-5'));
        $this->assertNull(ExcelItemImporter::money(''));

        $this->assertSame('6.08', ExcelItemImporter::decimal('6,08'));
        $this->assertSame('1.450', ExcelItemImporter::decimal('1.450')); // a dot is a decimal point for weights, never thousands
        $this->assertNull(ExcelItemImporter::decimal('6 gram'));

        $this->assertSame('15.00', ExcelItemImporter::discount('0.15')); // a spreadsheet percentage
        $this->assertSame('15.00', ExcelItemImporter::discount('15%'));
        $this->assertSame('15.00', ExcelItemImporter::discount('15'));
        $this->assertSame('12.50', ExcelItemImporter::discount('12,5'));
        $this->assertSame('100.00', ExcelItemImporter::discount('100'));
        $this->assertNull(ExcelItemImporter::discount('150'));
        $this->assertNull(ExcelItemImporter::discount('-5'));
        $this->assertNull(ExcelItemImporter::discount('abc'));

        $this->assertNull(ExcelItemImporter::date('1800-01-01'));
        $this->assertNull(ExcelItemImporter::date('bukan tanggal'));
    }

    public function test_invalid_numbers_are_emptied_and_flagged_not_turned_into_zero(): void
    {
        $rows = $this->rows($this->standardWorkbook([
            $this->jewelryRow(1, 'Berat salah', weight: 'abc'),
            $this->jewelryRow(2, 'Berat nol', weight: '0'),
            $this->jewelryRow(3, 'Berat kosong', weight: ''),
            $this->jewelryRow(4, 'Harga salah', cost: 'mahal'),
            $this->jewelryRow(5, 'Harga kosong', cost: ''),
        ]));

        foreach ([0, 1, 2] as $i) {
            $this->assertNull($rows[$i]['net_weight_g'], $rows[$i]['name']);
            $this->assertSame('check', $rows[$i]['status']);
        }
        $this->assertNull($rows[3]['acquisition_cost']);
        $this->assertSame('check', $rows[3]['status']);
        $this->assertNull($rows[4]['acquisition_cost']);
        $this->assertSame('ok', $rows[4]['status']); // an empty price is simply unknown
    }

    public function test_a_purity_conflict_uses_the_karat_and_marks_the_row_to_check(): void
    {
        $rows = $this->rows($this->standardWorkbook([
            $this->jewelryRow(1, 'Konflik', purity: '750/17K'),
            $this->jewelryRow(2, 'Cocok', purity: '750/18K', date: '2024-01-02'),
            $this->jewelryRow(3, 'Tak terbaca', purity: 'emas muda', date: '2024-01-03'),
            $this->jewelryRow(4, 'Kosong', purity: '', date: '2024-01-04'),
        ]));

        $this->assertSame('check', $rows[0]['status']);
        $this->assertSame('17', $rows[0]['purity_input']);
        $this->assertStringContainsString('750/17K', $rows[0]['notes'][0]);
        $this->assertSame('ok', $rows[1]['status']);
        $this->assertSame('check', $rows[2]['status']);
        $this->assertNull($rows[2]['purity_source']);
        $this->assertSame('check', $rows[3]['status']);
    }

    public function test_the_discount_column_is_read_when_it_exists(): void
    {
        $header = [...$this->headerRow(), 'POTONGAN'];
        $path = $this->workbook(['JUAL BELI' => [$header, [...$this->jewelryRow(1, 'Cincin V', date: '2026-05-22'), '0.15'], [...$this->jewelryRow(2, 'Bangle', date: '2026-06-01'), '10%']]]);

        $rows = $this->rows($path);

        $this->assertSame(['15.00', '10.00'], array_column($rows, 'resale_discount_percent'));
    }

    public function test_formulas_are_never_evaluated_or_used_as_values(): void
    {
        $rows = $this->rows($this->standardWorkbook([
            $this->jewelryRow(1, '=HYPERLINK("http://x","klik")'), // a formula in the name: the row is skipped
            $this->jewelryRow(2, 'Aman', cost: '=1+1'),             // a formula in the price: treated as empty
            $this->jewelryRow(3, 'Aman juga', store: '=cmd|x', date: '2024-01-03'),
        ]));

        $this->assertSame(['Aman', 'Aman juga'], array_column($rows, 'name'));
        $this->assertNull($rows[0]['acquisition_cost']);
        $this->assertNull($rows[1]['store']);
    }

    public function test_title_lines_repeated_headers_total_lines_and_group_headings_are_skipped(): void
    {
        $path = $this->workbook(['Daftar berdasarkan toko' => [
            ['1. Toko Emas Mulia Gold Palembang', '', '', '', '', '', '', '', ''],
            $this->headerRow(),
            $this->jewelryRow(1, 'Bangle mata WG'),
            ['Total', '', '', '', '', '', '=SUM(G2:G3)', '=SUM(H2:H3)', '='],
            ['2. Semar Nusantara Tegal', '', '', '', '', '', '', '', ''],
            $this->headerRow(),
            $this->jewelryRow(1, 'Cincin pita', 'Semar', 'Cincin', '2026-05-23', '17K', '1.1', '2182400'),
            ['Total', '', '', '', '', '', '', '', ''],
            [],
        ]]);

        $this->assertSame(['Bangle mata WG', 'Cincin pita'], array_column($this->rows($path), 'name'));
    }

    public function test_the_sheet_named_daftar_perhiasan_is_preferred_and_other_sheets_are_offered(): void
    {
        $path = $this->workbook([
            'Ringkasan Dashboard' => [['REKAPITULASI'], ['Total Koleksi', '28']],
            'JUAL BELI' => [$this->headerRow(), $this->jewelryRow(1, 'Cincin V')],
            'Daftar Perhiasan' => [$this->headerRow(), $this->jewelryRow(1, 'Gelang kaku')],
        ]);

        $result = app(ExcelItemImporter::class)->read($path, $this->user->id);

        $this->assertSame('Daftar Perhiasan', $result['default']);
        $this->assertSame(['JUAL BELI', 'Daftar Perhiasan'], array_keys($result['sheets'])); // the dashboard sheet has no header: ignored
    }

    public function test_a_workbook_without_a_recognizable_sheet_is_refused_with_a_message(): void
    {
        $path = $this->workbook(['Catatan' => [['Halo'], ['Ini bukan daftar perhiasan']]]);

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('Tidak ada sheet yang dikenali');

        app(ExcelItemImporter::class)->read($path, $this->user->id);
    }

    public function test_a_file_that_is_not_a_workbook_is_refused_without_leaking_anything(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bad').'.xlsx';
        file_put_contents($path, 'bukan berkas excel, rahasia-isi');

        try {
            app(ExcelItemImporter::class)->read($path, $this->user->id);
            $this->fail('Expected an ImportException');
        } catch (ImportException $e) {
            $this->assertStringContainsString('tidak bisa dibaca', $e->getMessage());
            $this->assertStringNotContainsString('rahasia-isi', $e->getMessage());
        } finally {
            @unlink($path);
        }
    }

    public function test_more_than_500_rows_in_a_sheet_is_refused_and_exactly_500_is_fine(): void
    {
        $many = fn (int $n) => array_map(fn ($i) => $this->jewelryRow($i, "Barang {$i}", date: '2024-01-01'), range(1, $n));

        $this->assertCount(500, $this->rows($this->standardWorkbook($many(500))));

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('lebih dari 500 baris');
        $this->rows($this->standardWorkbook($many(501)));
    }

    public function test_a_name_longer_than_160_characters_is_an_error_row_that_cannot_be_selected(): void
    {
        $rows = $this->rows($this->standardWorkbook([$this->jewelryRow(1, str_repeat('a', 161))]));

        $this->assertSame('error', $rows[0]['status']);
        $this->assertFalse($rows[0]['selected']);
    }

    public function test_a_row_matching_an_existing_item_is_a_duplicate_and_not_selected(): void
    {
        // Same name, date, net weight and price as the file row below.
        Item::factory()->jewelry()->create([
            'user_id' => $this->user->id, 'name' => 'Gelang kaku putih', 'owned_since' => '2023-04-24',
            'net_weight_g' => '6.0800', 'acquisition_cost' => '4895000.00',
        ]);

        $rows = $this->rows($this->standardWorkbook([
            $this->jewelryRow(1, 'gelang KAKU putih', date: '2023-04-24', weight: '6.08', cost: '4895000'), // name case does not matter
            $this->jewelryRow(2, 'Gelang lain', date: '2023-04-24', weight: '6.08', cost: '4895000'),
        ]));

        $this->assertSame('duplicate', $rows[0]['status']);
        $this->assertFalse($rows[0]['selected']);
        $this->assertStringContainsString('sudah ada di koleksi', $rows[0]['notes'][0]);
        $this->assertSame('ok', $rows[1]['status']);
    }

    public function test_another_owners_items_do_not_make_a_duplicate(): void
    {
        Item::factory()->jewelry()->create([
            'user_id' => User::factory()->create()->id, 'name' => 'Gelang kaku putih', 'owned_since' => '2023-04-24',
            'net_weight_g' => '6.0800', 'acquisition_cost' => '4895000.00',
        ]);

        $rows = $this->rows($this->standardWorkbook([$this->jewelryRow(1, 'Gelang kaku putih', date: '2023-04-24', weight: '6.08', cost: '4895000')]));

        $this->assertSame('ok', $rows[0]['status']);
    }

    public function test_a_repeated_row_in_the_same_sheet_is_a_duplicate_of_the_first(): void
    {
        $rows = $this->rows($this->standardWorkbook([
            $this->jewelryRow(1, 'Gelang'),
            $this->jewelryRow(2, 'Gelang'),
        ]));

        $this->assertSame('ok', $rows[0]['status']);
        $this->assertSame('duplicate', $rows[1]['status']);
        $this->assertStringContainsString('baris 4', $rows[1]['notes'][0]);
    }
}
