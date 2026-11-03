<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZakatPeriod;
use App\Services\Zakat\ZakatRuleSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesZakatCollections;
use Tests\TestCase;

class ZakatMadhhabsTest extends TestCase
{
    use CreatesZakatCollections, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_the_form_and_the_modal_data_offer_all_four_madhhab_from_the_rule_sheets(): void
    {
        $expected = ['hanafi', 'syafii', 'maliki', 'hanbali'];
        $this->assertSame($expected, ZakatRuleSet::supported());

        $check = fn (Assert $p) => $p
            ->where('madhhabs', fn ($list) => collect($list)->pluck('key')->all() === $expected)
            ->where('madhhabs', fn ($list) => collect($list)->every(fn ($m) => ! empty($m['references']) && ! empty($m['version'])))
            ->where('madhhabs', fn ($list) => collect($list)->pluck('label')->all() === ['Hanafi', "Syafi'i", 'Maliki', 'Hanbali']);

        $this->get('/zakat/hitung')->assertInertia($check); // the form's radio list
        $this->get('/zakat')->assertInertia($check);        // the penjelasan-mazhab modal's data
    }

    public function test_a_preview_works_for_maliki_and_hanbali(): void
    {
        $this->dueBars($this->user);

        foreach (['maliki' => 'Maliki', 'hanbali' => 'Hanbali'] as $madhhab => $label) {
            $this->get('/zakat/hitung?'.http_build_query($this->inputs($madhhab)))->assertInertia(fn (Assert $p) => $p
                ->where('form_errors', [])
                ->where('result.madhhab', $madhhab)
                ->where('result.madhhab_label', $label)
                ->where('result.outcome', 'due')
                ->where('result.nisab_g', '85')
                ->has('result.references'));
        }
        $this->assertSame(0, ZakatPeriod::count());
    }

    public function test_a_saved_period_for_maliki_and_hanbali_stores_the_rule_version(): void
    {
        $this->dueBars($this->user);

        foreach (['maliki', 'hanbali'] as $madhhab) {
            $this->post('/zakat/periode', $this->inputs($madhhab))->assertSessionHasNoErrors();
        }

        $periods = ZakatPeriod::orderBy('created_at')->get();
        $this->assertSame(['maliki', 'hanbali'], $periods->pluck('madhhab')->all());
        foreach ($periods as $period) {
            $this->assertSame(ZakatRuleSet::for($period->madhhab)->version, $period->rule_version);
            $this->assertSame('complete', $period->status);
            $this->assertSame($this->user->id, $period->user_id);

            $this->get("/zakat/periode/{$period->id}")->assertInertia(fn (Assert $p) => $p
                ->where('period.madhhab', $period->madhhab)
                ->where('period.rule_outdated', false)
                ->has('period.references'));
        }
    }

    public function test_an_unknown_madhhab_is_still_refused(): void
    {
        $this->dueBars($this->user);

        $this->post('/zakat/periode', $this->inputs('xyz'))->assertSessionHasErrors('madhhab');
        $this->assertSame(0, ZakatPeriod::count());
    }
}
