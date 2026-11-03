<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Models\ZakatPeriod;
use App\Services\GoldCalculator;
use App\Services\Zakat\ZakatCalculator;
use App\Services\Zakat\ZakatInput;
use App\Services\Zakat\ZakatItem;
use App\Services\Zakat\ZakatResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZakatPeriodSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function calculate(User $user, string $madhhab = 'hanafi'): ZakatResult
    {
        $calc = new GoldCalculator;
        $items = Item::where('user_id', $user->id)->with('units')->get()
            ->map(fn (Item $i) => ZakatItem::fromItem($i, $calc))->all();

        return (new ZakatCalculator)->calculate(new ZakatInput($madhhab, '2026-11-03', $items, '1500000'));
    }

    private function hundredGramBars(User $user): Item
    {
        $item = Item::factory()->create(['user_id' => $user->id, 'unit_weight_g' => '10.0000']);
        $item->units()->create(['serial_number' => null, 'quantity' => 10]);

        return $item;
    }

    public function test_period_keeps_its_figures_when_the_collection_changes_later(): void
    {
        $user = User::factory()->create();
        $item = $this->hundredGramBars($user);

        $period = ZakatPeriod::fromResult($this->calculate($user), $user->id);
        $period->save();
        $frozen = $period->fresh()->toArray();

        $this->assertSame('complete', $frozen['status']);
        $this->assertSame(0, bccomp('2.499750', $period->fresh()->zakat_g, 6)); // 99.99 g x 2.5%

        // Change the item, then delete it. The stored period must not move.
        $item->update(['unit_weight_g' => '1.0000', 'name' => 'Diubah']);
        $this->assertNotSame($frozen['eligible_g'], $this->calculate($user)->eligibleG);
        $item->delete();

        $this->assertSame($frozen, $period->fresh()->toArray());
        $this->assertSame($item->id, $period->fresh()->contributions[0]['item_id']);
    }

    public function test_owner_comes_from_the_caller_and_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->hundredGramBars($user);

        $period = ZakatPeriod::fromResult($this->calculate($user), $user->id);
        $period->save();
        $this->assertSame($user->id, $period->fresh()->user_id);

        $forged = new ZakatPeriod(['user_id' => $other->id, 'madhhab' => 'hanafi']);
        $this->assertNull($forged->user_id);
    }

    public function test_needs_review_is_stored_without_an_amount(): void
    {
        $user = User::factory()->create();
        Item::factory()->create(['user_id' => $user->id, 'unit_weight_g' => null]); // no weight, no units

        $period = ZakatPeriod::fromResult($this->calculate($user), $user->id);
        $period->save();
        $stored = $period->fresh();

        $this->assertSame('needs_review', $stored->status);
        $this->assertNull($stored->eligible_g);
        $this->assertNull($stored->zakat_g);
        $this->assertNull($stored->zakat_rupiah);
        $this->assertSame('item_needs_data', $stored->reasons[0]['code']);
        $this->assertNotEmpty($stored->steps);
    }

    public function test_decimals_survive_as_strings_without_float_rounding(): void
    {
        $user = User::factory()->create();
        $this->hundredGramBars($user);

        $stored = ZakatPeriod::fromResult($this->calculate($user), $user->id);
        $stored->save();
        $stored = $stored->fresh();

        $this->assertIsString($stored->nisab_g);
        $this->assertIsString($stored->zakat_g);
        $this->assertSame('1500000.00', $stored->price_per_gram);
        $this->assertSame('0.025000', $stored->rate);
    }

    public function test_item_haul_start_wins_over_owned_since_when_building_the_input(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create([
            'user_id' => $user->id, 'owned_since' => '2024-01-15', 'owned_since_certainty' => 'exact',
            'haul_start' => '2023-05-05', 'haul_start_certainty' => 'approximate',
        ]);

        $zi = ZakatItem::fromItem($item, new GoldCalculator);

        $this->assertSame('2023-05-05', $zi->date);
        $this->assertSame('approximate', $zi->dateCertainty);
    }

    public function test_deleting_the_user_removes_their_periods(): void
    {
        $user = User::factory()->create();
        ZakatPeriod::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertSame(0, ZakatPeriod::count());
    }
}
