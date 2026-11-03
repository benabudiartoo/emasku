<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZakatPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesZakatCollections;
use Tests\TestCase;

class ZakatIsolationTest extends TestCase
{
    use CreatesZakatCollections, RefreshDatabase;

    private User $a;

    private User $b;

    private ZakatPeriod $periodB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = User::factory()->create();
        $this->b = User::factory()->create();
        $this->periodB = ZakatPeriod::factory()->create(['user_id' => $this->b->id]);
    }

    public function test_owner_a_gets_404_for_owner_b_period(): void
    {
        $this->actingAs($this->a);

        $this->get('/zakat/periode/'.$this->periodB->id)->assertNotFound();
    }

    public function test_an_unknown_period_is_404(): void
    {
        $this->actingAs($this->a);

        $this->get('/zakat/periode/01HZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
    }

    public function test_the_owner_can_open_their_own_period(): void
    {
        $mine = ZakatPeriod::factory()->create(['user_id' => $this->a->id]);
        $this->actingAs($this->a);

        $this->get('/zakat/periode/'.$mine->id)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Zakat/Periode')->where('period.id', $mine->id));
    }

    public function test_the_overview_lists_only_own_periods(): void
    {
        $mine = ZakatPeriod::factory()->create(['user_id' => $this->a->id]);
        $this->actingAs($this->a);

        $this->get('/zakat')->assertInertia(fn (Assert $p) => $p
            ->has('periods', 1)
            ->where('periods.0.id', $mine->id));
    }

    public function test_saving_never_creates_a_period_for_someone_else(): void
    {
        $this->dueBars($this->a);
        $this->actingAs($this->a);

        $this->post('/zakat/periode', $this->inputs('hanafi', ['user_id' => $this->b->id]));

        $this->assertSame(1, ZakatPeriod::where('user_id', $this->a->id)->count());
        $this->assertSame(1, ZakatPeriod::where('user_id', $this->b->id)->count()); // only the one created in setUp
    }

    public function test_guests_are_redirected_to_login_on_every_zakat_route(): void
    {
        $this->get('/zakat')->assertRedirect('/login');
        $this->get('/zakat/hitung')->assertRedirect('/login');
        $this->get('/zakat/periode/'.$this->periodB->id)->assertRedirect('/login');
        $this->post('/zakat/periode', $this->inputs())->assertRedirect('/login');
        $this->assertSame(1, ZakatPeriod::count());
    }

    public function test_the_policy_is_the_owner_policy(): void
    {
        $this->assertInstanceOf(\App\Policies\OwnerPolicy::class, \Illuminate\Support\Facades\Gate::getPolicyFor(ZakatPeriod::class));
    }
}
