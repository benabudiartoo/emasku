<?php

namespace Tests\Unit;

use App\Services\Zakat\UnsupportedMadhhabException;
use App\Services\Zakat\ZakatResult;
use App\Services\Zakat\ZakatRuleSet;
use Tests\TestCase;
use Tests\Unit\Concerns\BuildsZakatInput;

class ZakatComparisonTest extends TestCase
{
    use BuildsZakatInput;

    public function test_same_collection_gives_different_results_per_rule_sheet(): void
    {
        // 82 g of bars plus a worn 6 g ring: Hanafi counts the ring (88 g, over 87.48), Syafi'i does not (82 g, under 85).
        $items = [$this->bar('bar', '82'), $this->ring('ring', '6', 'worn')];

        $hanafi = $this->calculate('hanafi', $items);
        $syafii = $this->calculate('syafii', $items);

        $this->assertSame(ZakatResult::DUE, $hanafi->outcome);
        $this->assertSame0('88', $hanafi->eligibleG);
        $this->assertSame0('2.2', $hanafi->zakatG);

        $this->assertSame(ZakatResult::BELOW_NISAB, $syafii->outcome);
        $this->assertSame0('82', $syafii->eligibleG);
        $this->assertSame0('3', $syafii->shortfallG);
    }

    public function test_four_madhhab_over_one_collection_each_follow_their_own_sheet(): void
    {
        // 82 g of bars plus a worn 6 g ring: only Hanafi counts the ring and has a nisab (87.48) that 88 g clears.
        $items = [$this->bar('bar', '82'), $this->ring('ring', '6', 'worn')];

        $expected = ['hanafi' => ['88', 'due'], 'syafii' => ['82', 'below_nisab'], 'maliki' => ['82', 'below_nisab'], 'hanbali' => ['82', 'below_nisab']];
        foreach ($expected as $madhhab => [$eligible, $outcome]) {
            $r = $this->calculate($madhhab, $items);

            $this->assertSame0($eligible, $r->eligibleG, $madhhab);
            $this->assertSame($outcome, $r->outcome, $madhhab);
        }
    }

    public function test_every_offered_madhhab_gives_the_result_its_own_sheet_predicts(): void
    {
        // Driven by the rule sheets: whatever sheets exist, the result must follow their nisab and jewelry rule.
        $items = [$this->bar('bar', '84'), $this->ring('worn-ring', '5', 'worn'), $this->ring('saved-ring', '2', 'investment')];

        foreach (ZakatRuleSet::supported() as $madhhab) {
            $sheet = ZakatRuleSet::for($madhhab);
            $eligible = bcadd('86', $sheet->wornJewelryZakatable ? '5' : '0', 10); // bar 84 + saved ring 2 (+ worn 5)
            $due = bccomp($eligible, $sheet->nisabG, 10) >= 0;

            $r = $this->calculate($madhhab, $items);

            $this->assertSame0($eligible, $r->eligibleG, $madhhab);
            $this->assertSame($due ? ZakatResult::DUE : ZakatResult::BELOW_NISAB, $r->outcome, $madhhab);
            $this->assertSame($sheet->version, $r->ruleVersion, $madhhab);
        }
    }

    public function test_nisab_differs_per_sheet_for_the_same_gold(): void
    {
        // 86 g of bars: over the Syafi'i nisab (85) but under the Hanafi one (87.48).
        $items = [$this->bar('bar', '86')];

        $this->assertSame(ZakatResult::DUE, $this->calculate('syafii', $items)->outcome);
        $this->assertSame(ZakatResult::BELOW_NISAB, $this->calculate('hanafi', $items)->outcome);
    }

    public function test_held_for_others_is_never_counted_by_any_madhhab(): void
    {
        $items = [$this->bar('mine', '100'), $this->bar('theirs', '500', '2024-01-10', 'exact', 'held_for_others')];

        foreach (['hanafi', 'syafii'] as $madhhab) {
            $r = $this->calculate($madhhab, $items);

            $this->assertSame0('100', $r->eligibleG, $madhhab);
            $this->assertFalse(collect($r->contributions)->firstWhere('item_id', 'theirs')['included']);
        }
    }

    public function test_result_names_the_madhhab_rule_version_and_references(): void
    {
        foreach (['hanafi', 'syafii'] as $madhhab) {
            $r = $this->calculate($madhhab, [$this->bar('bar', '100')]);

            $this->assertSame($madhhab, $r->madhhab);
            $this->assertSame('2026-11-03.1', $r->ruleVersion);
            $this->assertNotEmpty($r->references);
            $this->assertNotEmpty($r->references[0]['url']);
        }
    }

    public function test_steps_are_reproducible_from_their_own_figures(): void
    {
        $r = $this->calculate('hanafi', [$this->bar('bar', '100')], price: '1000000');

        $amount = collect($r->steps)->firstWhere('rule', 'amount')['figures'];
        $this->assertSame0($r->zakatG, bcmul($amount['eligible_g'], $amount['rate'], 10));

        $price = collect($r->steps)->firstWhere('rule', 'price')['figures'];
        $this->assertSame($r->zakatRupiah, \App\Services\GoldCalculator::round(bcmul($amount['zakat_g'], $price['price_per_gram'], 10), 2));

        foreach ($r->steps as $step) {
            $this->assertNotSame('', $step['reference'] ?? '', 'every step names the rule or reference behind it');
        }
    }

    public function test_only_madhhab_with_complete_sheets_are_offered(): void
    {
        $this->assertSame(['hanafi', 'syafii', 'maliki', 'hanbali'], ZakatRuleSet::supported());
    }

    public function test_madhhab_without_a_rule_sheet_is_refused(): void
    {
        foreach (['unknown', 'xyz', "syafi'i"] as $madhhab) {
            try {
                $this->calculate($madhhab, [$this->bar('bar', '100')]);
                $this->fail("Expected {$madhhab} to be refused");
            } catch (UnsupportedMadhhabException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_sheet_without_references_is_not_complete(): void
    {
        config(['zakat_rules.madhhabs.hanafi.references' => []]);

        $this->assertSame(['syafii', 'maliki', 'hanbali'], ZakatRuleSet::supported());
        $this->expectException(UnsupportedMadhhabException::class);
        ZakatRuleSet::for('hanafi');
    }
}
