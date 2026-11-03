<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZakatPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesZakatCollections;
use Tests\TestCase;

class ZakatNavigationTest extends TestCase
{
    use CreatesZakatCollections, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_the_summary_card_is_empty_before_the_first_calculation(): void
    {
        $this->dueBars($this->user);

        $this->get('/ringkasan')->assertInertia(fn (Assert $p) => $p->where('zakat', null));
    }

    public function test_the_summary_card_follows_the_latest_saved_period(): void
    {
        $this->dueBars($this->user);
        ZakatPeriod::factory()->create(['user_id' => $this->user->id, 'valuation_date' => '2025-01-01', 'madhhab' => 'hanafi']);
        ZakatPeriod::factory()->create([
            'user_id' => $this->user->id, 'valuation_date' => '2026-01-01', 'madhhab' => 'syafii',
            'status' => 'needs_review', 'eligible_g' => null, 'zakat_g' => null,
        ]);

        $this->get('/ringkasan')->assertInertia(fn (Assert $p) => $p
            ->where('zakat.status', 'needs_review')
            ->where('zakat.valuation_date', '2026-01-01')
            ->where('zakat.zakat_g', null));
    }

    public function test_the_summary_card_never_shows_another_owners_period(): void
    {
        $this->dueBars($this->user);
        ZakatPeriod::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->get('/ringkasan')->assertInertia(fn (Assert $p) => $p->where('zakat', null));
    }

    public function test_the_app_shell_links_the_zakat_tab_for_mobile_and_desktop(): void
    {
        // The shell renders one TABS list into both the bottom bar and the top nav, so one entry covers both.
        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.tsx'));

        $this->assertStringContainsString("{ href: '/zakat', label: 'Zakat'", $layout);
        $this->assertStringContainsString('TABS.map', $layout);
        $this->assertSame(2, substr_count($layout, 'TABS.map('), 'bottom bar and top nav both render TABS');
    }

    public function test_the_summary_card_links_to_the_zakat_page(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Ringkasan/Index.tsx'));

        $this->assertStringContainsString('href="/zakat"', $page);
        $this->assertStringContainsString('Status zakat', $page);
    }
}
