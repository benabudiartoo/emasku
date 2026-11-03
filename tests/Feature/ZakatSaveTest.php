<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Models\ZakatPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesZakatCollections;
use Tests\TestCase;

class ZakatSaveTest extends TestCase
{
    use CreatesZakatCollections, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_save_recomputes_on_the_server_for_the_signed_in_owner(): void
    {
        $this->dueBars($this->user);

        $response = $this->post('/zakat/periode', $this->inputs('syafii', ['price' => '1000000']));

        $period = ZakatPeriod::firstOrFail();
        $response->assertRedirect('/zakat/periode/'.$period->id);
        $response->assertSessionHas('toast', ['type' => 'success', 'message' => 'Perhitungan disimpan']);
        $this->assertSame($this->user->id, $period->user_id);
        $this->assertSame('syafii', $period->madhhab);
        $this->assertSame('complete', $period->status);
        $this->assertSame(0, bccomp('2.4997500000', $period->zakat_g, 10));
        $this->assertSame('2499750.00', $period->zakat_rupiah); // 2.49975 g x 1,000,000
        $this->assertSame('1000000.00', $period->price_per_gram);
    }

    public function test_client_supplied_results_and_owner_are_ignored(): void
    {
        $other = User::factory()->create();
        $this->dueBars($this->user);

        $this->post('/zakat/periode', $this->inputs('hanafi', [
            'user_id' => $other->id,
            'zakat_g' => '999',
            'zakat_rupiah' => '999999',
            'eligible_g' => '1',
            'status' => 'needs_review',
            'outcome' => 'below_nisab',
            'steps' => [['rule' => 'x', 'text' => 'palsu']],
        ]))->assertSessionHasNoErrors();

        $period = ZakatPeriod::firstOrFail();
        $this->assertSame($this->user->id, $period->user_id);
        $this->assertSame('complete', $period->status);
        $this->assertSame(0, bccomp('2.4997500000', $period->zakat_g, 10));
        $this->assertNull($period->zakat_rupiah);
        $this->assertNotSame('palsu', $period->steps[0]['text'] ?? null);
        $this->assertSame(0, ZakatPeriod::where('user_id', $other->id)->count());
    }

    public function test_needs_review_can_be_saved_without_an_amount(): void
    {
        $item = $this->dueBars($this->user);
        $item->update(['unit_weight_g' => null]);

        $this->post('/zakat/periode', $this->inputs())->assertSessionHasNoErrors();

        $period = ZakatPeriod::firstOrFail();
        $this->assertSame('needs_review', $period->status);
        $this->assertNull($period->eligible_g);
        $this->assertNull($period->zakat_g);
        $this->assertSame('item_needs_data', $period->reasons[0]['code']);
    }

    public function test_invalid_inputs_save_nothing(): void
    {
        $this->dueBars($this->user);

        $this->post('/zakat/periode', $this->inputs('maliki'))->assertSessionHasErrors('madhhab');
        $this->post('/zakat/periode', ['madhhab' => 'hanafi'])->assertSessionHasErrors('date');
        $this->post('/zakat/periode', $this->inputs('hanafi', ['price' => '0']))->assertSessionHasErrors('price');

        $this->assertSame(0, ZakatPeriod::count());
    }

    public function test_jewelry_confirmation_is_required_on_save_too(): void
    {
        Item::factory()->jewelry()->create(['user_id' => $this->user->id, 'purpose' => 'investment']);

        $this->post('/zakat/periode', $this->inputs())->assertSessionHasErrors('jewelry_confirmed');
        $this->assertSame(0, ZakatPeriod::count());

        $this->post('/zakat/periode', $this->inputs('hanafi', ['jewelry_confirmed' => '1']))->assertSessionHasNoErrors();
        $this->assertSame(1, ZakatPeriod::count());
    }

    public function test_the_saved_period_stays_the_same_after_the_collection_changes(): void
    {
        $item = $this->dueBars($this->user);
        $this->post('/zakat/periode', $this->inputs());
        $period = ZakatPeriod::firstOrFail();

        $item->update(['unit_weight_g' => '1.0000', 'name' => 'Diubah']);
        $this->get("/zakat/periode/{$period->id}")->assertInertia(fn (Assert $p) => $p
            ->where('period.outcome', 'due')
            ->where('period.zakat_g', '2.4998')
            ->where('period.contributions.0.name', 'Batangan Antam 10 g'));

        $item->delete();
        $this->get("/zakat/periode/{$period->id}")->assertInertia(fn (Assert $p) => $p
            ->where('period.zakat_g', '2.4998')
            ->where('period.contributions.0.linkable', false)); // the item is gone, so it is not linked
    }

    public function test_the_detail_page_carries_references_and_flags_changed_rules(): void
    {
        $this->dueBars($this->user);
        $this->post('/zakat/periode', $this->inputs('hanafi'));
        $period = ZakatPeriod::firstOrFail();

        $this->get("/zakat/periode/{$period->id}")->assertInertia(fn (Assert $p) => $p
            ->where('period.rule_outdated', false)
            ->where('period.rule_version', '2026-11-03.1')
            ->has('period.references')
            ->where('period.contributions.0.linkable', true));

        config(['zakat_rules.madhhabs.hanafi.version' => '9999-01-01.1']);
        $this->get("/zakat/periode/{$period->id}")->assertInertia(fn (Assert $p) => $p
            ->where('period.rule_outdated', true)
            ->where('period.rule_version', '2026-11-03.1')); // the stored version is kept
    }
}
