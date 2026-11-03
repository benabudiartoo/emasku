<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesZakatCollections;
use Tests\TestCase;

class SummaryChartsTest extends TestCase
{
    use CreatesZakatCollections, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function charts(): array
    {
        $charts = null;
        $this->get('/ringkasan')->assertInertia(function (Assert $page) use (&$charts) {
            $charts = $page->toArray()['props']['charts'];

            return $page;
        });

        return $charts;
    }

    public function test_kind_totals_add_up_to_the_summary_total(): void
    {
        $this->dueBars($this->user); // 99.99 g
        Item::factory()->jewelry()->create(['user_id' => $this->user->id]); // (8 - 2) g x 0.75 = 4.5 g

        $charts = $this->charts();

        $this->assertSame([['label' => 'Batangan', 'pure_gold_g' => '99.9900'], ['label' => 'Perhiasan', 'pure_gold_g' => '4.5000']], $charts['by_kind']);
        $this->get('/ringkasan')->assertInertia(fn (Assert $p) => $p->where('pure_gold_g', '104.4900'));
    }

    public function test_held_for_others_incomplete_items_and_other_owners_are_left_out(): void
    {
        $this->dueBars($this->user);
        $this->bars($this->user, '100.0000', 5, null, ['ownership' => 'held_for_others', 'held_for' => 'Paman']);
        Item::factory()->create(['user_id' => $this->user->id, 'name' => 'Belum ditimbang', 'unit_weight_g' => null]);
        $this->bars(User::factory()->create(), '100.0000', 5);

        $charts = $this->charts();

        $this->assertSame([['label' => 'Batangan', 'pure_gold_g' => '99.9900']], $charts['by_kind']);
        $this->assertSame('99.9900', end($charts['growth']['points'])['cumulative_g']);
        $this->assertSame(0, $charts['growth']['undated_count']); // an incomplete item is not "undated", it is not counted at all
    }

    public function test_a_kind_without_complete_items_has_no_row_instead_of_a_zero(): void
    {
        $this->dueBars($this->user);

        $this->assertSame(['Batangan'], array_column($this->charts()['by_kind'], 'label'));
    }

    public function test_locations_show_the_five_largest_then_the_rest_grouped(): void
    {
        foreach ([70, 60, 50, 40, 30, 20, 10] as $i => $grams) {
            $place = Location::factory()->create(['user_id' => $this->user->id, 'name' => "Lokasi {$grams}"]);
            $this->bars($this->user, "{$grams}.0000", 1, null, ['location_id' => $place->id]);
        }
        $this->bars($this->user, '5.0000', 1); // no location

        $rows = $this->charts()['by_location'];

        $this->assertSame(['Lokasi 70', 'Lokasi 60', 'Lokasi 50', 'Lokasi 40', 'Lokasi 30', 'Lainnya'], array_column($rows, 'label'));
        // Lainnya = Lokasi 20 (19.998) + Lokasi 10 (9.999) + Tanpa lokasi (4.9995)
        $this->assertSame('34.9965', end($rows)['pure_gold_g']);
    }

    public function test_items_without_a_location_are_shown_as_tanpa_lokasi(): void
    {
        $this->dueBars($this->user);

        $this->assertSame([['label' => 'Tanpa lokasi', 'pure_gold_g' => '99.9900']], $this->charts()['by_location']);
    }

    public function test_growth_is_cumulative_in_date_order_and_counts_undated_and_approximate(): void
    {
        $this->bars($this->user, '10.0000', 2, '2025-03-01', ['owned_since_certainty' => 'approximate']); // 19.998
        $this->bars($this->user, '10.0000', 1, '2024-01-10'); // 9.999
        $this->bars($this->user, '10.0000', 1, null, ['owned_since' => null, 'owned_since_certainty' => 'unknown']); // 9.999, no date

        $growth = $this->charts()['growth'];

        $this->assertSame([
            ['date' => '2024-01-10', 'cumulative_g' => '9.9990'],
            ['date' => '2025-03-01', 'cumulative_g' => '29.9970'],
        ], $growth['points']);
        $this->assertSame(1, $growth['undated_count']);
        $this->assertSame(1, $growth['approximate_count']);
    }

    public function test_items_on_the_same_date_share_one_point(): void
    {
        $this->bars($this->user, '10.0000', 1, '2024-01-10');
        $this->bars($this->user, '10.0000', 1, '2024-01-10');

        $this->assertSame([['date' => '2024-01-10', 'cumulative_g' => '19.9980']], $this->charts()['growth']['points']);
    }

    public function test_an_empty_collection_has_empty_charts(): void
    {
        $charts = $this->charts();

        $this->assertSame([], $charts['by_kind']);
        $this->assertSame([], $charts['by_location']);
        $this->assertSame([], $charts['growth']['points']);
    }

    public function test_the_sum_is_rounded_once_not_per_item(): void
    {
        // Two bars of 0.33335 g pure each: 0.6667 rounded once, not 0.3334 + 0.3334 = 0.6668.
        $this->bars($this->user, '0.333383', 1, '2024-01-10', ['purity_fraction' => '0.999900'])
            ->update(['unit_weight_g' => '0.33338334']);
        $this->bars($this->user, '0.333383', 1, '2024-01-11', ['purity_fraction' => '0.999900'])
            ->update(['unit_weight_g' => '0.33338334']);

        $charts = $this->charts();
        $each = bcmul('0.33338334', '0.9999', 10); // 0.3333500...
        $expected = \App\Services\GoldCalculator::round(bcmul($each, '2', 10), 4);

        $this->assertSame($expected, $charts['by_kind'][0]['pure_gold_g']);
    }
}
