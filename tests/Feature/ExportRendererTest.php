<?php

namespace Tests\Feature;

use App\Services\Export\Fmt;
use App\Services\Export\PdfRenderer;
use App\Services\Export\Report;
use App\Services\Export\ReportSection;
use App\Services\Export\XlsxRenderer;
use Tests\Feature\Concerns\ReadsXlsx;
use Tests\TestCase;

class ExportRendererTest extends TestCase
{
    use ReadsXlsx;

    private function report(array $rows = [['Batang', '12,3456']]): Report
    {
        return new Report(
            title: 'Uji ekspor',
            filename: 'uji',
            facts: ['Tanggal' => '3 Nov 2026'],
            sections: [new ReportSection('Daftar', ['Nama', 'Berat (g)'], $rows, 'Kosong.')],
            notes: ['Catatan kaki'],
        );
    }

    public function test_the_xlsx_opens_and_reads_back_what_was_written(): void
    {
        $rows = $this->readXlsx((new XlsxRenderer)->render($this->report()));

        $this->assertSame(['Uji ekspor'], $rows[0]);
        $this->assertTrue($this->sheetHas($rows, 'Tanggal'));
        $this->assertTrue($this->sheetHas($rows, 'Nama'));
        $this->assertContains(['Batang', '12,3456'], $rows); // a weight stays text, it is not turned into a float
        $this->assertTrue($this->sheetHas($rows, 'Catatan kaki'));
    }

    public function test_cells_that_look_like_formulas_are_neutralized(): void
    {
        $evil = [['=SUM(A1:A9)', '+1'], ['-2', '@cmd'], ['Biasa', "\t=x"]];
        $rows = $this->readXlsx((new XlsxRenderer)->render($this->report($evil)));

        $this->assertContains(["'=SUM(A1:A9)", "'+1"], $rows);
        $this->assertContains(["'-2", "'@cmd"], $rows);
        $this->assertContains(['Biasa', "'\t=x"], $rows);
        $this->assertSame("'=A1", XlsxRenderer::safe('=A1'));
        $this->assertSame('Aman', XlsxRenderer::safe('Aman'));
        $this->assertSame('', XlsxRenderer::safe(''));
    }

    public function test_an_empty_table_shows_its_empty_text_instead_of_a_header(): void
    {
        $rows = $this->readXlsx((new XlsxRenderer)->render($this->report([])));

        $this->assertTrue($this->sheetHas($rows, 'Kosong.'));
        $this->assertFalse($this->sheetHas($rows, 'Berat (g)'));
    }

    public function test_the_pdf_is_a_real_pdf(): void
    {
        $pdf = (new PdfRenderer)->render($this->report());

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_the_pdf_view_escapes_text(): void
    {
        $html = view('exports.report', ['report' => $this->report([['<script>alert(1)</script>', '1']]), 'printed' => '03-11-2026 10:00'])->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Dicetak 03-11-2026 10:00', $html);
    }

    public function test_number_formatting_matches_the_screens(): void
    {
        $this->assertSame('4,50', Fmt::gram('4.5000'));
        $this->assertSame('1.234.567,80', Fmt::gram('1234567.8000'));
        $this->assertSame('85', Fmt::gram('85.0000', 0));
        $this->assertSame('', Fmt::gram(null));
        $this->assertSame('Rp 2.286.035', Fmt::rupiah('2286035'));
        $this->assertSame('2,5', Fmt::percent('0.025000'));
        $this->assertSame('3 Nov 2026', Fmt::date('2026-11-03'));
        $this->assertSame('750 ‰', Fmt::purity('permil', '750.0000'));
        $this->assertSame('Kadar belum diisi', Fmt::purity(null, null));
        $this->assertSame('berat per keping, jumlah keping', Fmt::missing(['unit_weight_g', 'quantity']));
        $this->assertSame('Nisab 85,4 g pada 2026-11-03 Rp 1.500', Fmt::stepText('Nisab 85.4 g pada 2026-11-03 Rp 1.500'));
    }

    public function test_a_missing_amount_is_never_written_as_zero(): void
    {
        $review = ['status' => 'needs_review', 'outcome' => null, 'zakat_g' => null, 'zakat_rupiah' => null];
        $this->assertSame('Perlu peninjauan', Fmt::zakatOutcome($review));
        $this->assertSame('Perlu peninjauan', Fmt::zakatGrams($review));
        $this->assertSame('Perlu peninjauan', Fmt::zakatRupiah($review));

        $below = ['status' => 'complete', 'outcome' => 'below_nisab', 'zakat_g' => null, 'zakat_rupiah' => null];
        $this->assertSame('Belum wajib', Fmt::zakatGrams($below));

        $due = ['status' => 'complete', 'outcome' => 'due', 'zakat_g' => '2.4998', 'zakat_rupiah' => null];
        $this->assertSame('2,4998', Fmt::zakatGrams($due));
        $this->assertSame('Harga belum diisi', Fmt::zakatRupiah($due));
    }
}
