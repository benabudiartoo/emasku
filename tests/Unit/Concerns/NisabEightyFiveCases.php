<?php

namespace Tests\Unit\Concerns;

use App\Services\Zakat\ZakatResult;

/**
 * Cases shared by the sheets that use the 85 g nisab and leave worn jewelry out (Syafi'i-like rules: Maliki, Hanbali).
 * The using class provides madhhab() and label(); every figure comes from that madhhab's own rule sheet.
 */
trait NisabEightyFiveCases
{
    use BuildsZakatInput;

    abstract protected function madhhab(): string;

    abstract protected function label(): string;

    public function test_the_sheet_is_complete_and_names_itself_and_its_references(): void
    {
        $r = $this->calculate($this->madhhab(), [$this->bar('bar', '100')]);

        $this->assertSame($this->madhhab(), $r->madhhab);
        $this->assertSame('85', $r->nisabG);
        $this->assertSame('0.025', $r->rate);
        $this->assertNotEmpty($r->ruleVersion);
        $this->assertNotEmpty($r->references);
        foreach ($r->references as $reference) {
            $this->assertNotEmpty($reference['name']);
            $this->assertStringStartsWith('https://', $reference['url']);
            $this->assertNotEmpty($reference['accessed']);
        }
    }

    public function test_worn_jewelry_is_excluded_with_a_stated_reason(): void
    {
        $r = $this->calculate($this->madhhab(), [$this->bar('bar', '100'), $this->ring('ring', '4.5', 'worn')]);

        $this->assertSame(ZakatResult::DUE, $r->outcome);
        $this->assertSame0('100', $r->eligibleG);
        $this->assertSame0('2.5', $r->zakatG);

        $ring = collect($r->contributions)->firstWhere('item_id', 'ring');
        $this->assertFalse($ring['included']);
        $this->assertStringContainsString($this->label(), $ring['reason']);
    }

    public function test_jewelry_kept_as_savings_is_counted(): void
    {
        $r = $this->calculate($this->madhhab(), [$this->bar('bar', '90'), $this->ring('ring', '10', 'investment')]);

        $this->assertSame0('100', $r->eligibleG);
        $this->assertSame(ZakatResult::DUE, $r->outcome);
    }

    public function test_undecided_jewelry_purpose_needs_review(): void
    {
        $r = $this->calculate($this->madhhab(), [$this->bar('bar', '100'), $this->ring('ring', '4.5', 'undecided')]);

        $this->assertSame(ZakatResult::NEEDS_REVIEW, $r->status);
        $this->assertReasonCodes(['jewelry_purpose_undecided'], $r);
        $this->assertNull($r->zakatG);
    }

    public function test_nisab_is_exactly_85_g_and_a_shortfall_of_any_size_is_below(): void
    {
        $at = $this->calculate($this->madhhab(), [$this->bar('bar', '85')]);
        $below = $this->calculate($this->madhhab(), [$this->bar('bar', '84.9999')]);

        $this->assertSame(ZakatResult::DUE, $at->outcome);
        $this->assertSame0('2.125', $at->zakatG);
        $this->assertSame(ZakatResult::BELOW_NISAB, $below->outcome);
        $this->assertSame0('0.0001', $below->shortfallG); // no small-shortfall tolerance is applied
        $this->assertNull($below->zakatG);
    }

    public function test_approximate_date_needs_review_and_a_manual_haul_start_resolves_it(): void
    {
        $items = [$this->bar('bar', '100', '2015-06-01', 'approximate')];

        $r = $this->calculate($this->madhhab(), $items);
        $this->assertSame(ZakatResult::NEEDS_REVIEW, $r->status);
        $this->assertReasonCodes(['date_uncertain'], $r);
        $this->assertNull($r->zakatG);

        $manual = $this->calculate($this->madhhab(), $items, manualHaul: '2024-01-10');
        $this->assertSame(ZakatResult::DUE, $manual->outcome);
        $this->assertSame('2024-01-10', $manual->haulStart);
    }

    public function test_held_for_others_is_never_counted(): void
    {
        $r = $this->calculate($this->madhhab(), [$this->bar('mine', '100'), $this->bar('theirs', '500', '2024-01-10', 'exact', 'held_for_others')]);

        $this->assertSame0('100', $r->eligibleG);
        $this->assertFalse(collect($r->contributions)->firstWhere('item_id', 'theirs')['included']);
    }

    public function test_gold_added_during_the_haul_needs_review_because_the_rule_is_unverified(): void
    {
        $r = $this->calculate($this->madhhab(), [$this->bar('first', '90', '2024-01-10'), $this->bar('added', '10', '2024-06-01')]);

        $this->assertSame(ZakatResult::NEEDS_REVIEW, $r->status);
        $this->assertReasonCodes(['addition_during_haul'], $r);
        $this->assertNull($r->zakatG);
    }

    public function test_haul_is_one_hijri_year_and_rupiah_needs_a_price(): void
    {
        $r = $this->calculate($this->madhhab(), [$this->bar('bar', '100', '2024-01-10')], price: '1000000');

        $this->assertSame('1445-06-28', $r->haulStartHijri);
        $this->assertSame('1446-06-28', $r->haulEndHijri);
        $this->assertSame('2500000.00', $r->zakatRupiah);
        $this->assertNull($this->calculate($this->madhhab(), [$this->bar('bar', '100')])->zakatRupiah);
    }
}
