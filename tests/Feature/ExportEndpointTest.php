<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Models\ZakatPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Concerns\CreatesZakatCollections;
use Tests\Feature\Concerns\ReadsXlsx;
use Tests\TestCase;

class ExportEndpointTest extends TestCase
{
    use CreatesZakatCollections, ReadsXlsx, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function savedPeriod(User $owner): ZakatPeriod
    {
        $this->actingAs($owner)->post('/zakat/periode', $this->inputs('syafii', ['price' => '1000000']))->assertRedirect();

        return ZakatPeriod::where('user_id', $owner->id)->firstOrFail();
    }

    /** @return list<string> */
    private function urls(ZakatPeriod $period): array
    {
        $urls = [];
        foreach (['xlsx', 'pdf'] as $f) {
            array_push($urls, "/ekspor/koleksi.$f", "/ekspor/ringkasan.$f", "/ekspor/zakat.$f", "/ekspor/zakat/{$period->id}.$f");
        }

        return $urls;
    }

    public function test_a_guest_is_redirected_to_login_for_every_export(): void
    {
        $period = ZakatPeriod::factory()->create();

        foreach ($this->urls($period) as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_every_export_downloads_in_both_formats_with_safe_headers(): void
    {
        $this->actingAs($this->user);
        $this->dueBars($this->user);
        $period = $this->savedPeriod($this->user);

        foreach ($this->urls($period) as $url) {
            $response = $this->get($url)->assertOk();
            $isXlsx = str_ends_with($url, '.xlsx');

            $response->assertHeader('Content-Type', $isXlsx ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'), $url);
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            $this->assertMatchesRegularExpression('/^attachment; filename="[A-Za-z0-9._-]+\.(xlsx|pdf)"$/', $response->headers->get('Content-Disposition'), $url);
            $this->assertStringNotContainsString($this->user->email, $response->headers->get('Content-Disposition'));

            $body = $response->getContent();
            $isXlsx ? $this->readXlsx($body) : $this->assertStringStartsWith('%PDF-', $body);
        }
    }

    public function test_the_xlsx_contains_the_owners_items_and_not_anyone_elses(): void
    {
        $this->dueBars($this->user);
        $this->bars(User::factory()->create(), '10.0000', 3, null, ['name' => 'Milik orang lain']);

        $rows = $this->readXlsx($this->actingAs($this->user)->get('/ekspor/koleksi.xlsx')->getContent());

        $this->assertTrue($this->sheetHas($rows, 'Batangan Antam 10 g'));
        $this->assertFalse($this->sheetHas($rows, 'Milik orang lain'));
        $this->assertTrue($this->sheetHas($rows, '99,99'));
    }

    public function test_the_collection_export_carries_the_active_filters(): void
    {
        $this->dueBars($this->user);
        Item::factory()->jewelry()->create(['user_id' => $this->user->id]);

        $rows = $this->readXlsx($this->actingAs($this->user)->get('/ekspor/koleksi.xlsx?jenis=jewelry')->getContent());

        $this->assertTrue($this->sheetHas($rows, 'Cincin Kawin'));
        $this->assertFalse($this->sheetHas($rows, 'Batangan Antam 10 g'));
    }

    public function test_another_owners_period_is_refused_in_both_formats(): void
    {
        $this->dueBars($this->user);
        $theirs = $this->savedPeriod($this->user);
        $intruder = User::factory()->create();

        foreach (['xlsx', 'pdf'] as $f) {
            $this->actingAs($intruder)->get("/ekspor/zakat/{$theirs->id}.$f")->assertNotFound();
        }
    }

    public function test_the_zakat_list_only_has_this_owners_periods(): void
    {
        $this->dueBars($this->user);
        $mine = $this->savedPeriod($this->user);
        ZakatPeriod::factory()->create(['user_id' => User::factory()->create()->id, 'madhhab' => 'hanbali']);

        $rows = $this->readXlsx($this->actingAs($this->user)->get('/ekspor/zakat.xlsx')->getContent());

        $this->assertTrue($this->sheetHas($rows, '1')); // "Jumlah perhitungan"
        $this->assertTrue($this->sheetHas($rows, 'Syafi\'i'));
        $this->assertFalse($this->sheetHas($rows, 'Hanbali'));
        $this->assertSame($mine->madhhab, 'syafii');
    }

    public function test_other_formats_are_not_found(): void
    {
        $this->actingAs($this->user);
        $period = ZakatPeriod::factory()->create(['user_id' => $this->user->id]);

        foreach (['csv', 'docx', 'html', 'XLSX'] as $f) {
            $this->get("/ekspor/koleksi.$f")->assertNotFound();
            $this->get("/ekspor/zakat/{$period->id}.$f")->assertNotFound();
        }
        $this->get('/ekspor/koleksi')->assertNotFound();
    }

    public function test_an_owner_past_the_rate_limit_gets_429_and_others_are_unaffected(): void
    {
        config(['export.throttle_per_minute' => 2]);
        RateLimiter::clear('export');
        $other = User::factory()->create();

        $this->actingAs($this->user)->get('/ekspor/ringkasan.xlsx')->assertOk();
        $this->actingAs($this->user)->get('/ekspor/ringkasan.pdf')->assertOk();
        $this->actingAs($this->user)->get('/ekspor/ringkasan.xlsx')->assertStatus(429);
        $this->actingAs($other)->get('/ekspor/ringkasan.xlsx')->assertOk();
    }

    public function test_files_are_complete_even_when_amounts_are_hidden_in_settings(): void
    {
        $this->user->update(['hide_amounts' => true]);
        $this->dueBars($this->user);
        $period = $this->savedPeriod($this->user);

        $summary = $this->readXlsx($this->actingAs($this->user)->get('/ekspor/ringkasan.xlsx')->getContent());
        $this->assertTrue($this->sheetHas($summary, '99,99'));

        $zakat = $this->readXlsx($this->actingAs($this->user)->get("/ekspor/zakat/{$period->id}.xlsx")->getContent());
        $this->assertTrue($this->sheetHas($zakat, '2,4998'));
        $this->assertTrue($this->sheetHas($zakat, 'Rp 2.499.750'));
    }

    public function test_a_formula_like_item_name_is_neutralized_in_the_download(): void
    {
        $this->bars($this->user, '10.0000', 1, null, ['name' => '=HYPERLINK("http://x")']);

        $rows = $this->readXlsx($this->actingAs($this->user)->get('/ekspor/koleksi.xlsx')->getContent());

        $this->assertTrue($this->sheetHas($rows, '\'=HYPERLINK("http://x")'));
        $this->assertFalse($this->sheetHas($rows, '=HYPERLINK("http://x")'));
    }
}
