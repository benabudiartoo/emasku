<?php

namespace Tests\Unit;

use App\Services\Zakat\ZakatResult;
use Tests\TestCase;
use Tests\Unit\Concerns\BuildsZakatInput;

class ZakatSyafiiTest extends TestCase
{
    use BuildsZakatInput;

    public function test_worn_jewelry_is_excluded_with_a_stated_reason(): void
    {
        $r = $this->calculate('syafii', [$this->bar('bar', '100'), $this->ring('ring', '4.5', 'worn')]);

        $this->assertSame(ZakatResult::DUE, $r->outcome);
        $this->assertSame0('100', $r->eligibleG);
        $this->assertSame0('2.5', $r->zakatG);

        $ring = collect($r->contributions)->firstWhere('item_id', 'ring');
        $this->assertFalse($ring['included']);
        $this->assertStringContainsString("Syafi'i", $ring['reason']);
    }

    public function test_jewelry_kept_as_savings_is_counted(): void
    {
        $r = $this->calculate('syafii', [$this->bar('bar', '90'), $this->ring('ring', '10', 'investment')]);

        $this->assertSame0('100', $r->eligibleG);
        $this->assertSame(ZakatResult::DUE, $r->outcome);
    }

    public function test_undecided_jewelry_purpose_needs_review(): void
    {
        foreach (['undecided', null] as $purpose) {
            $r = $this->calculate('syafii', [$this->bar('bar', '100'), $this->ring('ring', '4.5', $purpose)]);

            $this->assertSame(ZakatResult::NEEDS_REVIEW, $r->status);
            $this->assertReasonCodes(['jewelry_purpose_undecided'], $r);
            $this->assertNull($r->zakatG);
        }
    }

    public function test_exactly_at_nisab_85_is_due_and_just_below_is_not(): void
    {
        $this->assertSame(ZakatResult::DUE, $this->calculate('syafii', [$this->bar('bar', '85')])->outcome);
        $this->assertSame(ZakatResult::BELOW_NISAB, $this->calculate('syafii', [$this->bar('bar', '84.9999')])->outcome);
    }

    public function test_gold_added_during_the_haul_needs_review(): void
    {
        // The first bar reaches the nisab on 2024-01-10; the second is added a year later, inside the haul.
        $r = $this->calculate('syafii', [$this->bar('first', '90', '2024-01-10'), $this->bar('added', '10', '2024-06-01')]);

        $this->assertSame(ZakatResult::NEEDS_REVIEW, $r->status);
        $this->assertReasonCodes(['addition_during_haul'], $r);
        $this->assertSame('added', $r->reasons[0]['item_id']);
        $this->assertNull($r->zakatG);
    }

    public function test_items_that_reach_the_nisab_together_on_one_day_are_not_additions(): void
    {
        $r = $this->calculate('syafii', [$this->bar('a', '50', '2024-01-10'), $this->bar('b', '50', '2024-01-10')]);

        $this->assertSame(ZakatResult::DUE, $r->outcome);
        $this->assertSame('2024-01-10', $r->haulStart);
    }

    public function test_haul_end_is_one_hijri_year_after_the_start(): void
    {
        $r = $this->calculate('syafii', [$this->bar('bar', '100', '2024-01-10')]);

        $this->assertSame('1445-06-28', $r->haulStartHijri);
        $this->assertSame('1446-06-28', $r->haulEndHijri);
    }
}
