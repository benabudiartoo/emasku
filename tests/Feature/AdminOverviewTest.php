<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Models\ZakatPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesZakatCollections;
use Tests\TestCase;

class AdminOverviewTest extends TestCase
{
    use CreatesZakatCollections, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin Utama', 'email' => 'admin@contoh.test']);
    }

    private function page(array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->get('/admin'.($query ? '?'.http_build_query($query) : ''));
    }

    public function test_totals_and_per_user_counts_are_right(): void
    {
        $ani = User::factory()->create(['name' => 'Ani', 'email' => 'ani@contoh.test']);
        $budi = User::factory()->create(['name' => 'Budi', 'email' => 'budi@contoh.test']);
        $this->bars($ani, '10.0000', 2);
        $this->bars($ani, '10.0000', 1);
        $this->bars($budi, '10.0000', 1);
        ZakatPeriod::factory()->create(['user_id' => $ani->id]);

        $this->page()->assertInertia(function (Assert $p) {
            $p->where('totals.users', 3)->where('totals.items', 3)->where('totals.zakat_periods', 1);
            $p->where('users.total', 3);

            return $p->where('users.data', function ($rows) {
                $by = collect($rows)->keyBy('email');

                return $by['ani@contoh.test']['items_count'] === 2
                    && $by['ani@contoh.test']['zakat_periods_count'] === 1
                    && $by['budi@contoh.test']['items_count'] === 1
                    && $by['budi@contoh.test']['zakat_periods_count'] === 0
                    && $by['admin@contoh.test']['items_count'] === 0
                    && $by['admin@contoh.test']['is_admin'] === true;
            });
        });
    }

    public function test_the_thirty_day_totals_use_registration_and_last_login(): void
    {
        User::factory()->create(['created_at' => now()->subDays(40), 'last_login_at' => now()->subDays(40)]);
        User::factory()->create(['created_at' => now()->subDays(40), 'last_login_at' => now()->subDays(2)]);
        User::factory()->create(['created_at' => now()->subDays(3), 'last_login_at' => null]);
        $this->admin->forceFill(['last_login_at' => now()])->save(); // new today and active

        $this->page()->assertInertia(fn (Assert $p) => $p
            ->where('totals.users', 4)
            ->where('totals.active_30d', 2) // the admin and the one who logged in 2 days ago
            ->where('totals.new_30d', 2)); // the admin and the one created 3 days ago
    }

    public function test_a_user_who_never_logged_in_has_no_date(): void
    {
        User::factory()->create(['email' => 'baru@contoh.test', 'last_login_at' => null]);

        $this->page()->assertInertia(fn (Assert $p) => $p->where('users.data', fn ($rows) => collect($rows)->firstWhere('email', 'baru@contoh.test')['last_login_at'] === null));
    }

    public function test_private_data_never_reaches_the_admin_page(): void
    {
        $owner = User::factory()->create(['email' => 'pemilik@contoh.test']);
        $place = Location::factory()->create(['user_id' => $owner->id, 'name' => 'BRANKAS-RAHASIA-77']);
        $item = $this->bars($owner, '12.3456', 3, null, ['name' => 'EMAS-RAHASIA-99', 'brand' => 'MEREK-RAHASIA', 'notes' => 'CATATAN-RAHASIA', 'location_id' => $place->id, 'acquisition_cost' => '987654321.00']);
        $this->actingAs($owner)->post('/zakat/periode', ['date' => now()->toDateString(), 'madhhab' => 'syafii', 'price' => '1111111'])->assertRedirect();

        $html = $this->page()->assertOk()->getContent();
        $json = $this->actingAs($this->admin)->getJson('/admin', ['X-Inertia' => 'true'])->getContent();

        foreach ([$html, $json] as $body) {
            foreach (['EMAS-RAHASIA-99', 'MEREK-RAHASIA', 'CATATAN-RAHASIA', 'BRANKAS-RAHASIA-77', '12.3456', '987654321', '1111111', 'zakat_g', 'pure_gold', 'remember_token', 'password'] as $secret) {
                $this->assertStringNotContainsString($secret, $body, "leaked: {$secret}");
            }
        }
        $this->assertNotNull($item); // the data exists; it is just never shown
    }

    public function test_users_are_listed_newest_first_in_pages_of_25(): void
    {
        foreach (range(1, 29) as $i) {
            User::factory()->create(['email' => sprintf('u%02d@contoh.test', $i), 'created_at' => now()->subDays(100)->addMinutes($i)]);
        }

        $this->page()->assertInertia(fn (Assert $p) => $p
            ->where('users.total', 30)
            ->where('users.last_page', 2)
            ->where('users.data.0.email', 'admin@contoh.test') // newest
            ->where('users.data.1.email', 'u29@contoh.test')
            ->has('users.data', 25)
            ->where('users.next_url', fn ($url) => str_contains($url, 'page=2')));

        $this->page(['page' => 2])->assertInertia(fn (Assert $p) => $p->has('users.data', 5)->where('users.current_page', 2)->where('users.next_url', null));
    }
}
