<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Models\ZakatPeriod;
use App\Services\Export\CollectionReport;
use App\Services\Export\Fmt;
use App\Services\Export\SummaryReport;
use App\Services\Export\ZakatListReport;
use App\Services\Export\ZakatPeriodReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesZakatCollections;
use Tests\TestCase;

class ExportReportTest extends TestCase
{
    use CreatesZakatCollections, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function rowsOf(\App\Services\Export\Report $report, int $section = 0): array
    {
        return $report->sections[$section]->rows;
    }

    public function test_the_collection_report_has_exact_weights_and_a_total(): void
    {
        $this->dueBars($this->user); // 10 pieces x 10 g at 999.9 = 99.99 g pure gold

        $report = app(CollectionReport::class)->build($this->user->id, []);

        $this->assertSame('99,99', $report->facts['Total emas murni milik sendiri (g)']);
        $row = $this->rowsOf($report)[0];
        $this->assertSame(['Batangan Antam 10 g', 'Batangan', 'Milik sendiri', '10', '100,00', '999,9 ‰', '99,99', '', 'Lengkap', ''], $row);
    }

    public function test_the_item_note_is_exported_in_the_catatan_column(): void
    {
        $this->bars($this->user, '10.0000', 1, null, ['name' => 'Dengan catatan', 'notes' => "Disimpan di brankas\nbeli menjelang lebaran"]);
        $this->bars($this->user, '10.0000', 1, null, ['name' => 'Tanpa catatan', 'notes' => null]);

        $report = app(CollectionReport::class)->build($this->user->id, []);

        $this->assertSame('Catatan', $report->sections[0]->columns[9]);
        $this->assertSame("Disimpan di brankas\nbeli menjelang lebaran", collect($this->rowsOf($report))->firstWhere(0, 'Dengan catatan')[9]);
        $this->assertSame('', collect($this->rowsOf($report))->firstWhere(0, 'Tanpa catatan')[9]);
    }

    public function test_missing_data_is_named_and_never_written_as_zero_or_counted(): void
    {
        $this->dueBars($this->user);
        Item::factory()->create(['user_id' => $this->user->id, 'name' => 'Belum ditimbang', 'unit_weight_g' => null]);

        $report = app(CollectionReport::class)->build($this->user->id, []);

        $row = collect($this->rowsOf($report))->firstWhere(0, 'Belum ditimbang');
        $this->assertSame(Fmt::NEEDS, $row[4]); // net weight
        $this->assertSame(Fmt::NEEDS, $row[6]); // pure gold
        $this->assertStringStartsWith('Data diperlukan: ', $row[8]);
        $this->assertNotContains('0,00', $row);
        $this->assertSame('99,99', $report->facts['Total emas murni milik sendiri (g)']);
        $this->assertSame('1', $report->facts['Barang data kurang']);
    }

    public function test_items_held_for_others_are_marked_and_left_out_of_the_total(): void
    {
        $this->dueBars($this->user);
        $this->bars($this->user, '100.0000', 5, null, ['name' => 'Titipan', 'ownership' => 'held_for_others', 'held_for' => 'Paman']);

        $report = app(CollectionReport::class)->build($this->user->id, []);

        $this->assertSame('99,99', $report->facts['Total emas murni milik sendiri (g)']);
        $this->assertSame('Titipan (Paman)', collect($this->rowsOf($report))->firstWhere(0, 'Titipan')[2]);
    }

    public function test_the_collection_filters_are_respected_and_another_owners_items_never_appear(): void
    {
        $this->dueBars($this->user);
        Item::factory()->jewelry()->create(['user_id' => $this->user->id]);
        $this->bars(User::factory()->create(), '10.0000', 3, null, ['name' => 'Milik orang lain']);

        $all = app(CollectionReport::class)->build($this->user->id, []);
        $this->assertCount(2, $this->rowsOf($all));
        $this->assertNotContains('Milik orang lain', array_column($this->rowsOf($all), 0));

        $bars = app(CollectionReport::class)->build($this->user->id, ['jenis' => 'bar']);
        $this->assertSame(['Batangan'], array_unique(array_column($this->rowsOf($bars), 1)));
        $this->assertSame('Batangan', $bars->facts['Filter']);

        $jewelry = app(CollectionReport::class)->build($this->user->id, ['jenis' => 'jewelry']);
        $this->assertSame(['Cincin Kawin'], array_column($this->rowsOf($jewelry), 0));
        $this->assertSame('4,50', $jewelry->facts['Total emas murni milik sendiri (g)']); // (8 - 2) g x 0.75
    }

    public function test_an_empty_collection_still_builds_a_report(): void
    {
        $report = app(CollectionReport::class)->build($this->user->id, []);

        $this->assertSame([], $this->rowsOf($report));
        $this->assertSame('0', $report->facts['Jumlah barang']);
        $this->assertSame('0,00', $report->facts['Total emas murni milik sendiri (g)']); // a real total of nothing
    }

    public function test_the_summary_report_splits_pure_gold_by_kind_and_lists_missing_data(): void
    {
        $this->dueBars($this->user);
        Item::factory()->jewelry()->create(['user_id' => $this->user->id]);
        Item::factory()->create(['user_id' => $this->user->id, 'name' => 'Belum ditimbang', 'unit_weight_g' => null]);

        $report = app(SummaryReport::class)->build($this->user->id);

        $this->assertSame('104,49', $report->facts['Total emas murni (g)']); // 99.99 + 4.5
        $this->assertSame('106,00', $report->facts['Total berat emas (g)']); // 100 g of bars + 6 g net of the ring
        $this->assertSame('3', $report->facts['Barang aktif']);
        $this->assertContains(['Batangan', '2', '99,99'], $this->rowsOf($report, 0));
        $this->assertContains(['Perhiasan', '1', '4,50'], $this->rowsOf($report, 0));
        $this->assertSame('Belum ditimbang', $this->rowsOf($report, 1)[0][0]);
    }

    private function savedPeriod(string $madhhab = 'syafii'): ZakatPeriod
    {
        $this->post('/zakat/periode', $this->inputs($madhhab, ['price' => '1000000']))->assertRedirect();

        return ZakatPeriod::latest('created_at')->firstOrFail();
    }

    public function test_a_period_report_uses_the_stored_figures_even_after_the_collection_changes(): void
    {
        $this->dueBars($this->user);
        $period = $this->savedPeriod();

        $before = app(ZakatPeriodReport::class)->build($period);
        Item::where('user_id', $this->user->id)->delete();
        $after = app(ZakatPeriodReport::class)->build($period->fresh());

        $this->assertSame('2,4998', $before->facts['Zakat (g emas murni)']); // 99.99 g x 2.5%
        $this->assertSame('Wajib zakat', $before->facts['Hasil']);
        $this->assertSame('Rp 2.499.750', $before->facts['Zakat (rupiah)']);
        $this->assertSame($before->facts, $after->facts);
        $this->assertStringStartsWith('Zakat Syafi\'i - ', $before->title);
        $this->assertContains(Fmt::DISCLAIMER, $before->notes);
        $this->assertNotEmpty($this->rowsOf($before, 1)); // calculation steps
    }

    public function test_a_needs_review_period_has_reasons_and_no_amount(): void
    {
        $this->dueBars($this->user);
        Item::factory()->create(['user_id' => $this->user->id, 'name' => 'Belum ditimbang', 'unit_weight_g' => null]);
        $period = $this->savedPeriod();

        $report = app(ZakatPeriodReport::class)->build($period);

        $this->assertSame('Perlu peninjauan', $report->facts['Hasil']);
        $this->assertSame('Perlu peninjauan', $report->facts['Zakat (g emas murni)']);
        $this->assertSame('Perlu peninjauan', $report->facts['Zakat (rupiah)']);
        $this->assertSame('Alasan perlu peninjauan', $report->sections[1]->heading);
        $this->assertTrue(collect($report->notes)->contains(fn ($n) => str_contains($n, 'bukan nol')));
    }

    public function test_the_zakat_list_report_lists_only_this_owners_periods_newest_first(): void
    {
        $this->dueBars($this->user);
        $first = $this->savedPeriod('hanafi');
        $first->update(['valuation_date' => now()->subDays(10)->toDateString()]);
        $this->savedPeriod('syafii');

        $other = User::factory()->create();
        ZakatPeriod::factory()->create(['user_id' => $other->id]);

        $periods = ZakatPeriod::where('user_id', $this->user->id)->orderByDesc('valuation_date')->get();
        $report = app(ZakatListReport::class)->build($periods);

        $this->assertSame('2', $report->facts['Jumlah perhitungan']);
        $rows = $this->rowsOf($report);
        $this->assertCount(2, $rows);
        $this->assertSame('Syafi\'i', $rows[0][1]);
        $this->assertSame('Hanafi', $rows[1][1]);
    }
}
