<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\ItemUnit;
use App\Services\GoldCalculator;
use App\Services\GoldResult;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GoldCalculatorTest extends TestCase
{
    private function ring(array $attributes = []): Item
    {
        return (new Item)->forceFill($attributes + [
            'kind' => 'jewelry',
            'ownership' => 'own',
            'gross_weight_g' => '8.0000',
            'stone_state' => 'known',
            'stone_weight_g' => '2.0000',
            'net_weight_g' => null,
            'purity_fraction' => '0.750000',
        ]);
    }

    private function bar(?string $unitWeight, array $quantities, array $attributes = []): Item
    {
        $item = (new Item)->forceFill($attributes + [
            'kind' => 'bar',
            'ownership' => 'own',
            'unit_weight_g' => $unitWeight,
            'purity_fraction' => '0.999900',
        ]);
        $item->setRelation('units', new Collection(array_map(
            fn (int $q) => new ItemUnit(['quantity' => $q]),
            $quantities,
        )));

        return $item;
    }

    public function test_acceptance_case_3_ring_gives_4_5_g_pure_gold(): void
    {
        $calc = new GoldCalculator;
        $item = $this->ring();

        $net = $calc->netWeight($item);
        $pure = $calc->pureGold($item);

        $this->assertTrue($net->isComplete());
        $this->assertSame(0, bccomp($net->value, '6', 10));
        $this->assertTrue($pure->isComplete());
        $this->assertSame(0, bccomp($pure->value, '4.5', 10));
    }

    public function test_acceptance_case_3_unknown_stone_and_empty_net_is_flagged(): void
    {
        $item = $this->ring(['stone_state' => 'unknown', 'stone_weight_g' => null]);

        $net = (new GoldCalculator)->netWeight($item);
        $pure = (new GoldCalculator)->pureGold($item);

        $this->assertSame(GoldResult::NEEDS_DATA, $net->status);
        $this->assertNull($net->value);
        $this->assertSame(['stone_weight_g'], $net->missing);
        $this->assertSame(GoldResult::NEEDS_DATA, $pure->status);
        $this->assertNull($pure->value);
    }

    public function test_stone_none_uses_gross_as_net(): void
    {
        $item = $this->ring(['stone_state' => 'none', 'stone_weight_g' => null]);

        $net = (new GoldCalculator)->netWeight($item);

        $this->assertTrue($net->isComplete());
        $this->assertSame(0, bccomp($net->value, '8', 10));
    }

    public function test_known_stone_without_weight_needs_data(): void
    {
        $item = $this->ring(['stone_weight_g' => null]);

        $this->assertSame(['stone_weight_g'], (new GoldCalculator)->netWeight($item)->missing);
    }

    public function test_stored_net_weight_wins_even_with_unknown_stone(): void
    {
        $item = $this->ring([
            'stone_state' => 'unknown',
            'stone_weight_g' => null,
            'net_weight_g' => '5.5000',
        ]);

        $pure = (new GoldCalculator)->pureGold($item);

        $this->assertTrue($pure->isComplete());
        $this->assertSame(0, bccomp($pure->value, '4.125', 10));
    }

    public function test_missing_gross_and_missing_purity_are_listed_and_never_zero(): void
    {
        $item = $this->ring(['gross_weight_g' => null, 'purity_fraction' => null]);

        $pure = (new GoldCalculator)->pureGold($item);

        $this->assertNull($pure->value);
        $this->assertEqualsCanonicalizing(['gross_weight_g', 'purity_fraction'], $pure->missing);
    }

    public function test_stone_heavier_than_gross_needs_data_not_negative(): void
    {
        $item = $this->ring(['stone_weight_g' => '9.0000']);

        $net = (new GoldCalculator)->netWeight($item);

        $this->assertSame(GoldResult::NEEDS_DATA, $net->status);
        $this->assertNull($net->value);
    }

    public function test_bar_pure_gold_is_unit_weight_times_pieces_times_purity(): void
    {
        $item = $this->bar('10.0000', [1, 2]);

        $pure = (new GoldCalculator)->pureGold($item);

        $this->assertSame(0, bccomp($pure->value, '29.997', 10));
    }

    public function test_bar_without_weight_or_pieces_needs_data(): void
    {
        $calc = new GoldCalculator;

        $this->assertSame(['unit_weight_g'], $calc->netWeight($this->bar(null, [1]))->missing);
        $this->assertSame(['quantity'], $calc->netWeight($this->bar('5.0000', []))->missing);
    }

    public function test_summary_excludes_held_for_others_and_counts_incomplete_apart(): void
    {
        $own = $this->ring();
        $held = $this->ring(['ownership' => 'held_for_others']);
        $incomplete = $this->ring(['stone_state' => 'unknown', 'stone_weight_g' => null]);

        $summary = (new GoldCalculator)->summarize([$own, $held, $incomplete]);

        $this->assertSame(0, bccomp($summary['pure_gold'], '4.5', 10));
        $this->assertSame(1, $summary['complete']);
        $this->assertSame(1, $summary['incomplete']);
        $this->assertSame(2, $summary['counted']);
        $this->assertSame([$incomplete], $summary['incomplete_items']);
    }

    public function test_purity_fraction_conversions(): void
    {
        $this->assertSame('0.750000', GoldCalculator::purityFraction('permil', '750'));
        $this->assertSame('0.999900', GoldCalculator::purityFraction('permil', '999.9'));
        $this->assertSame('0.750000', GoldCalculator::purityFraction('percent', '75'));
        $this->assertSame('0.750000', GoldCalculator::purityFraction('karat', '18'));
    }

    public function test_purity_out_of_range_or_unknown_source_is_rejected(): void
    {
        foreach ([['permil', '0'], ['permil', '1001'], ['karat', '25'], ['percent', 'abc']] as [$source, $input]) {
            try {
                GoldCalculator::purityFraction($source, $input);
                $this->fail("Expected rejection for {$source} {$input}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        GoldCalculator::purityFraction('carat', '18');
    }

    public function test_round_is_half_up_for_display(): void
    {
        $this->assertSame('4.50', GoldCalculator::round('4.4999999999', 2));
        $this->assertSame('1.01', GoldCalculator::round('1.005', 2));
        $this->assertSame('-1.01', GoldCalculator::round('-1.005', 2));
    }
}
