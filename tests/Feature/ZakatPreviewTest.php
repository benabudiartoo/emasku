<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Models\ZakatPeriod;
use App\Services\Zakat\ZakatRuleSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesZakatCollections;
use Tests\TestCase;

class ZakatPreviewTest extends TestCase
{
    use CreatesZakatCollections, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_the_form_offers_exactly_the_madhhab_with_rule_sheets_and_no_result(): void
    {
        $this->get('/zakat/hitung')->assertInertia(fn (Assert $p) => $p
            ->component('Zakat/Hitung')
            ->where('result', null)
            ->where('madhhabs', fn ($list) => collect($list)->pluck('key')->all() === ZakatRuleSet::supported())
            ->where('inputs.madhhab', 'hanafi'));
    }

    public function test_a_valid_preview_is_computed_and_nothing_is_stored(): void
    {
        $this->dueBars($this->user);

        $this->get('/zakat/hitung?'.http_build_query($this->inputs('syafii')))
            ->assertInertia(fn (Assert $p) => $p
                ->where('form_errors', [])
                ->where('result.status', 'complete')
                ->where('result.outcome', 'due')
                ->where('result.madhhab', 'syafii')
                ->where('result.zakat_g', '2.4998')); // 99.99 g x 2.5% = 2.49975, shown to 4 decimals

        $this->assertSame(0, ZakatPeriod::count());
    }

    public function test_a_madhhab_without_a_rule_sheet_is_rejected(): void
    {
        $this->dueBars($this->user);

        foreach (['xyz', 'shafii', 'hanafii'] as $madhhab) {
            $this->get('/zakat/hitung?'.http_build_query($this->inputs($madhhab)))
                ->assertInertia(fn (Assert $p) => $p->where('result', null)->has('form_errors.madhhab'));
        }
    }

    public function test_invalid_inputs_give_messages_and_no_result(): void
    {
        $this->dueBars($this->user);

        $cases = [
            'date' => $this->inputs('hanafi', ['date' => now()->addDay()->toDateString()]),
            'price' => $this->inputs('hanafi', ['price' => '-5']),
            'haul_start' => $this->inputs('hanafi', ['haul_start' => now()->addDay()->toDateString()]),
        ];

        foreach ($cases as $field => $query) {
            $this->get('/zakat/hitung?'.http_build_query($query))
                ->assertInertia(fn (Assert $p) => $p->where('result', null)->has("form_errors.{$field}"));
        }
    }

    public function test_price_may_use_a_decimal_comma(): void
    {
        $this->dueBars($this->user);

        $this->get('/zakat/hitung?'.http_build_query($this->inputs('hanafi', ['price' => '1500000,5'])))
            ->assertInertia(fn (Assert $p) => $p->where('result.price_per_gram', '1500000.5')->whereNot('result.zakat_rupiah', null));
    }

    public function test_jewelry_confirmation_is_required_only_when_the_owner_has_jewelry(): void
    {
        $this->dueBars($this->user);
        $this->get('/zakat/hitung?'.http_build_query($this->inputs()))
            ->assertInertia(fn (Assert $p) => $p->where('has_jewelry', false)->where('form_errors', []));

        Item::factory()->jewelry()->create(['user_id' => $this->user->id, 'purpose' => 'investment']);

        $this->get('/zakat/hitung?'.http_build_query($this->inputs()))
            ->assertInertia(fn (Assert $p) => $p->where('has_jewelry', true)->where('result', null)->has('form_errors.jewelry_confirmed'));

        $this->get('/zakat/hitung?'.http_build_query($this->inputs('hanafi', ['jewelry_confirmed' => '1'])))
            ->assertInertia(fn (Assert $p) => $p->where('form_errors', [])->whereNot('result', null));
    }

    public function test_other_owners_items_never_contribute(): void
    {
        $other = User::factory()->create();
        $this->bars($other, '100.0000', 5); // 499.95 g, someone else's

        $this->get('/zakat/hitung?'.http_build_query($this->inputs()))
            ->assertInertia(fn (Assert $p) => $p
                ->where('result.outcome', 'below_nisab')
                ->where('result.eligible_g', '0.0000')
                ->where('result.contributions', []));
    }

    public function test_held_for_others_is_listed_as_excluded_not_counted(): void
    {
        $this->dueBars($this->user);
        $this->bars($this->user, '100.0000', 5, null, ['ownership' => 'held_for_others', 'held_for' => 'Paman']);

        $this->get('/zakat/hitung?'.http_build_query($this->inputs()))
            ->assertInertia(fn (Assert $p) => $p
                ->where('result.eligible_g', '99.9900')
                ->where('result.contributions', fn ($c) => collect($c)->where('included', false)->count() === 1));
    }

    public function test_the_price_button_is_offered_unless_the_feed_is_switched_off(): void
    {
        config(['goldprice.enabled' => false]);
        $this->get('/zakat/hitung')->assertInertia(fn (Assert $p) => $p
            ->where('price_feed.available', false)
            ->where('price_feed.attribution.name', 'Gold-API.com'));

        config(['goldprice.enabled' => true]);
        $this->get('/zakat/hitung')->assertInertia(fn (Assert $p) => $p
            ->where('price_feed.available', true)
            ->where('price_feed.attribution.url', 'https://gold-api.com'));
    }

    public function test_guests_are_sent_to_login(): void
    {
        auth()->logout();

        $this->get('/zakat/hitung')->assertRedirect('/login');
    }
}
