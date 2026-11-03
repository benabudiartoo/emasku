<?php

namespace Tests\Unit;

use App\Services\Zakat\ZakatResult;
use Tests\TestCase;
use Tests\Unit\Concerns\BuildsZakatInput;

class ZakatHanafiTest extends TestCase
{
    use BuildsZakatInput;

    public function test_worn_jewelry_counts_and_zakat_is_2_5_percent(): void
    {
        $r = $this->calculate('hanafi', [$this->bar('bar', '100'), $this->ring('ring', '4.5', 'worn')]);

        $this->assertSame(ZakatResult::COMPLETE, $r->status);
        $this->assertSame(ZakatResult::DUE, $r->outcome);
        $this->assertSame2('104.5', $r->eligibleG);
        $this->assertSame2('2.6125', $r->zakatG);
        $this->assertTrue(collect($r->contributions)->every(fn ($c) => $c['included']));
    }

    public function test_exactly_at_nisab_is_due(): void
    {
        $r = $this->calculate('hanafi', [$this->bar('bar', '87.48')]);

        $this->assertSame(ZakatResult::DUE, $r->outcome);
        $this->assertSame2('2.187', $r->zakatG);
    }

    public function test_just_below_nisab_is_not_due_and_shows_the_shortfall(): void
    {
        $r = $this->calculate('hanafi', [$this->bar('bar', '87.4799')]);

        $this->assertSame(ZakatResult::COMPLETE, $r->status);
        $this->assertSame(ZakatResult::BELOW_NISAB, $r->outcome);
        $this->assertNull($r->zakatG);
        $this->assertSame2('0.0001', $r->shortfallG);
    }

    public function test_haul_not_complete_is_not_due(): void
    {
        $r = $this->calculate('hanafi', [$this->bar('bar', '100', '2026-06-01')]);

        $this->assertSame(ZakatResult::HAUL_INCOMPLETE, $r->outcome);
        $this->assertNull($r->zakatG);
        $this->assertGreaterThan('2026-11-03', $r->haulEnd);
    }

    public function test_approximate_date_needs_review_and_gives_no_amount(): void
    {
        $r = $this->calculate('hanafi', [$this->bar('bar', '100', '2015-06-01', 'approximate')]);

        $this->assertSame(ZakatResult::NEEDS_REVIEW, $r->status);
        $this->assertNull($r->outcome);
        $this->assertNull($r->zakatG);
        $this->assertNull($r->eligibleG);
        $this->assertReasonCodes(['date_uncertain'], $r);
        $this->assertSame('bar', $r->reasons[0]['item_id']);
    }

    public function test_unknown_date_needs_review(): void
    {
        $r = $this->calculate('hanafi', [$this->bar('bar', '100', null, 'unknown')]);

        $this->assertSame(ZakatResult::NEEDS_REVIEW, $r->status);
        $this->assertReasonCodes(['date_uncertain'], $r);
    }

    public function test_manual_haul_start_replaces_the_date_certainty_reason(): void
    {
        $r = $this->calculate('hanafi', [$this->bar('bar', '100', '2015-06-01', 'approximate')], manualHaul: '2024-01-10');

        $this->assertSame(ZakatResult::COMPLETE, $r->status);
        $this->assertSame(ZakatResult::DUE, $r->outcome);
        $this->assertSame('2024-01-10', $r->haulStart);
        $this->assertContains('haul_start_manual', array_column($r->steps, 'rule'));
    }

    public function test_item_with_missing_data_needs_review_never_zero(): void
    {
        $r = $this->calculate('hanafi', [$this->bar('bar', '100'), $this->ring('ring', null, 'worn')]);

        $this->assertSame(ZakatResult::NEEDS_REVIEW, $r->status);
        $this->assertReasonCodes(['item_needs_data'], $r);
        $this->assertNull($r->zakatG);
    }

    public function test_rupiah_only_when_a_price_is_given(): void
    {
        $without = $this->calculate('hanafi', [$this->bar('bar', '100')]);
        $with = $this->calculate('hanafi', [$this->bar('bar', '100')], price: '1500000');

        $this->assertNull($without->zakatRupiah);
        $this->assertSame('3750000.00', $with->zakatRupiah); // 2.5 g x 1,500,000
    }

    public function test_item_owned_only_after_the_valuation_date_is_left_out(): void
    {
        $r = $this->calculate('hanafi', [$this->bar('old', '100', '2024-01-10'), $this->bar('future', '50', '2027-01-01')]);

        $this->assertSame2('100', $r->eligibleG);
        $this->assertSame('Dimiliki setelah tanggal penilaian.', collect($r->contributions)->firstWhere('item_id', 'future')['reason']);
    }

    private function assertSame2(string $expected, ?string $actual): void
    {
        $this->assertSame0($expected, $actual);
    }
}
