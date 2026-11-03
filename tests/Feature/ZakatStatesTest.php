<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZakatPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesZakatCollections;
use Tests\TestCase;

class ZakatStatesTest extends TestCase
{
    use CreatesZakatCollections, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function preview(array $inputs): \Illuminate\Testing\TestResponse
    {
        return $this->get('/zakat/hitung?'.http_build_query($inputs));
    }

    public function test_empty_overview_has_no_periods(): void
    {
        $this->get('/zakat')->assertInertia(fn (Assert $p) => $p
            ->component('Zakat/Index')
            ->where('periods', [])
            ->has('madhhabs', 4));
    }

    public function test_due_shows_grams_and_rupiah_only_with_a_price(): void
    {
        $this->dueBars($this->user);

        $this->preview($this->inputs('hanafi'))->assertInertia(fn (Assert $p) => $p
            ->where('result.outcome', 'due')->where('result.zakat_rupiah', null)->whereNot('result.zakat_g', null));

        $this->preview($this->inputs('hanafi', ['price' => '1500000']))->assertInertia(fn (Assert $p) => $p
            ->where('result.outcome', 'due')->where('result.zakat_rupiah', '3749625.00')); // 2.49975 g x 1,500,000
    }

    public function test_below_nisab_states_the_shortfall(): void
    {
        $this->bars($this->user, '10.0000', 1); // 9.999 g

        $this->preview($this->inputs('syafii'))->assertInertia(fn (Assert $p) => $p
            ->where('result.status', 'complete')
            ->where('result.outcome', 'below_nisab')
            ->where('result.zakat_g', null)
            ->where('result.shortfall_g', '75.0010')); // 85 - 9.999
    }

    public function test_haul_incomplete_states_when_the_haul_ends(): void
    {
        $this->bars($this->user, '10.0000', 10, now()->subMonths(3)->toDateString());

        $this->preview($this->inputs('hanafi'))->assertInertia(fn (Assert $p) => $p
            ->where('result.outcome', 'haul_incomplete')
            ->where('result.zakat_g', null)
            ->where('result.haul_end', fn ($end) => $end > now()->toDateString())
            ->whereNot('result.haul_end_hijri', null));
    }

    public function test_needs_review_lists_reasons_and_gives_no_amount(): void
    {
        $item = $this->bars($this->user, '10.0000', 10);
        $item->update(['unit_weight_g' => null]); // weight unknown

        $this->preview($this->inputs('hanafi'))->assertInertia(fn (Assert $p) => $p
            ->where('result.status', 'needs_review')
            ->where('result.outcome', null)
            ->where('result.eligible_g', null)
            ->where('result.zakat_g', null)
            ->where('result.zakat_rupiah', null)
            ->where('result.reasons.0.code', 'item_needs_data')
            ->where('result.reasons.0.item_id', $item->id));
    }

    public function test_approximate_date_needs_review_and_a_manual_haul_start_resolves_it(): void
    {
        $this->bars($this->user, '10.0000', 10, now()->subYears(5)->toDateString(), ['owned_since_certainty' => 'approximate']);

        $this->preview($this->inputs())->assertInertia(fn (Assert $p) => $p
            ->where('result.status', 'needs_review')->where('result.reasons.0.code', 'date_uncertain'));

        $this->preview($this->inputs('hanafi', ['haul_start' => now()->subYears(3)->toDateString()]))
            ->assertInertia(fn (Assert $p) => $p->where('result.status', 'complete')->where('result.outcome', 'due'));
    }

    public function test_the_overview_lists_saved_periods_newest_first_with_their_state(): void
    {
        $this->dueBars($this->user);
        ZakatPeriod::factory()->create(['user_id' => $this->user->id, 'valuation_date' => '2025-01-01', 'madhhab' => 'hanafi']);
        $newer = ZakatPeriod::factory()->create([
            'user_id' => $this->user->id, 'valuation_date' => '2026-01-01', 'status' => 'needs_review',
            'eligible_g' => null, 'zakat_g' => null, 'madhhab' => 'syafii',
        ]);

        $this->get('/zakat')->assertInertia(fn (Assert $p) => $p
            ->has('periods', 2)
            ->where('periods.0.id', $newer->id)
            ->where('periods.0.status', 'needs_review')
            ->where('periods.0.outcome', null)
            ->where('periods.0.zakat_g', null)
            ->where('periods.0.madhhab_label', "Syafi'i")
            ->where('periods.1.outcome', 'due'));
    }

    public function test_a_stored_period_derives_below_nisab_and_haul_incomplete(): void
    {
        $below = ZakatPeriod::factory()->create(['user_id' => $this->user->id, 'eligible_g' => '60.0000000000', 'zakat_g' => null]);
        $haul = ZakatPeriod::factory()->create(['user_id' => $this->user->id, 'eligible_g' => '100.0000000000', 'zakat_g' => null]);

        $this->get("/zakat/periode/{$below->id}")->assertInertia(fn (Assert $p) => $p
            ->where('period.outcome', 'below_nisab')->where('period.shortfall_g', '25.0000'));
        $this->get("/zakat/periode/{$haul->id}")->assertInertia(fn (Assert $p) => $p
            ->where('period.outcome', 'haul_incomplete')->where('period.shortfall_g', null));
    }
}
