<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\BuildsWorkbooks;
use Tests\TestCase;

class ImportFlowTest extends TestCase
{
    use BuildsWorkbooks, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        $this->cleanWorkbooks();
        parent::tearDown();
    }

    /** Three rows: a clean one, a purity conflict (750/17K), and another clean one. */
    private function threeRows(): array
    {
        return [
            $this->jewelryRow(1, 'Gelang kaku putih', 'Toko Emas Mulia Gold Palembang', 'Gelang', '2023-04-24', '17K', '6.08', '4895000'),
            $this->jewelryRow(2, 'Gelang Oval Glam', 'Signature CCM', 'Gelang', '2023-05-14', '750/17K', '7.43', '7000000'),
            $this->jewelryRow(3, 'Cincin pita', 'Semar Nusantara Tegal', 'Cincin', '2026-05-23', '18K', '1.1', '2182400'),
        ];
    }

    /** Uploads a workbook and returns the preview token. */
    private function preview(array $rows, string $name = 'Rekap perhiasan.xlsx'): string
    {
        $response = $this->post('/koleksi/impor', ['file' => $this->upload($this->standardWorkbook($rows), $name)]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        return basename($response->headers->get('Location'));
    }

    // ---- access -----------------------------------------------------------------------------------------------

    public function test_guests_are_sent_to_login_for_every_import_route(): void
    {
        auth()->logout();

        $this->get('/koleksi/impor')->assertRedirect('/login');
        $this->post('/koleksi/impor')->assertRedirect('/login');
        $this->get('/koleksi/impor/abc')->assertRedirect('/login');
        $this->post('/koleksi/impor/abc')->assertRedirect('/login');
        $this->delete('/koleksi/impor/abc')->assertRedirect('/login');
    }

    public function test_the_upload_page_opens_with_the_limits(): void
    {
        $this->get('/koleksi/impor')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Koleksi/Impor')->where('max_mb', 2)->where('max_rows', 500));
    }

    // ---- preview ----------------------------------------------------------------------------------------------

    public function test_the_preview_lists_the_rows_with_their_status_and_creates_nothing(): void
    {
        $token = $this->preview($this->threeRows());

        $this->get("/koleksi/impor/{$token}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Koleksi/ImporPratinjau')
            ->where('filename', 'Rekap perhiasan.xlsx')
            ->where('sheet', 'Daftar Perhiasan')
            ->where('counts', ['ok' => 2, 'check' => 1])
            ->has('rows', 3)
            ->where('rows.0.name', 'Gelang kaku putih')
            ->where('rows.0.status', 'ok')
            ->where('rows.1.status', 'check')
            ->where('rows.1.purity_input', '17')
            ->where('rows.0.selected', true));

        $this->assertSame(0, Item::count()); // nothing is stored by looking
    }

    public function test_another_sheet_can_be_chosen(): void
    {
        $path = $this->workbook([
            'Daftar Perhiasan' => [$this->headerRow(), $this->jewelryRow(1, 'Gelang kaku')],
            'JUAL BELI' => [$this->headerRow(), $this->jewelryRow(1, 'Cincin V', date: '2026-05-22'), $this->jewelryRow(2, 'Bangle', date: '2026-06-01')],
        ]);
        $token = basename($this->post('/koleksi/impor', ['file' => $this->upload($path)])->headers->get('Location'));

        $this->get("/koleksi/impor/{$token}")->assertInertia(fn (Assert $p) => $p->where('sheet', 'Daftar Perhiasan')->has('rows', 1)->has('sheets', 2));
        $this->get("/koleksi/impor/{$token}?sheet=JUAL+BELI")->assertInertia(fn (Assert $p) => $p->where('sheet', 'JUAL BELI')->has('rows', 2));
        $this->get("/koleksi/impor/{$token}?sheet=tidak-ada")->assertInertia(fn (Assert $p) => $p->where('sheet', 'Daftar Perhiasan')); // unknown sheet: the default
    }

    // ---- upload limits ----------------------------------------------------------------------------------------

    public function test_only_xlsx_files_are_accepted(): void
    {
        $this->post('/koleksi/impor', ['file' => UploadedFile::fake()->createWithContent('daftar.csv', "a,b\n1,2")])->assertSessionHasErrors('file');
        $this->post('/koleksi/impor', ['file' => UploadedFile::fake()->createWithContent('daftar.txt', 'halo')])->assertSessionHasErrors('file');
        $this->post('/koleksi/impor', [])->assertSessionHasErrors('file');
    }

    public function test_a_file_named_xlsx_that_is_not_a_zip_is_refused(): void
    {
        $this->post('/koleksi/impor', ['file' => $this->upload($this->textFile('ini bukan zip'))])->assertSessionHasErrors('file');
        $this->assertSame(0, Item::count());
    }

    private function textFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'txt').'.xlsx';
        file_put_contents($path, $content);
        $this->workbookFiles[] = $path;

        return $path;
    }

    public function test_a_file_over_2_mb_is_refused(): void
    {
        $this->post('/koleksi/impor', ['file' => UploadedFile::fake()->create('besar.xlsx', 2049, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])
            ->assertSessionHasErrors(['file' => 'Berkas lebih besar dari 2 MB.']);
    }

    public function test_more_than_500_rows_is_refused_with_a_message(): void
    {
        $rows = array_map(fn ($i) => $this->jewelryRow($i, "Barang {$i}", date: '2024-01-01'), range(1, 501));

        $this->post('/koleksi/impor', ['file' => $this->upload($this->standardWorkbook($rows))])
            ->assertSessionHasErrors('file');
        $this->assertStringContainsString('lebih dari 500 baris', session('errors')->first('file'));
        $this->assertSame(0, Item::count());
    }

    public function test_a_workbook_with_nothing_recognizable_is_refused_with_a_message(): void
    {
        $path = $this->workbook(['Catatan' => [['Halo']]]);

        $this->post('/koleksi/impor', ['file' => $this->upload($path)])->assertSessionHasErrors('file');
    }

    // ---- save -------------------------------------------------------------------------------------------------

    public function test_saving_creates_exactly_the_checked_rows_as_jewelry_of_this_owner(): void
    {
        $token = $this->preview($this->threeRows());

        $this->post("/koleksi/impor/{$token}", ['sheet' => 'Daftar Perhiasan', 'rows' => [4, 6]]) // the first and third data rows
            ->assertRedirect('/koleksi')
            ->assertSessionHas('toast.message', '2 barang diimpor');

        $this->assertSame(['Cincin pita', 'Gelang kaku putih'], Item::where('user_id', $this->user->id)->orderBy('name')->pluck('name')->all());

        $item = Item::where('name', 'Gelang kaku putih')->firstOrFail();
        $this->assertSame('jewelry', $item->kind);
        $this->assertSame('own', $item->ownership);
        $this->assertSame('gelang', $item->jewelry_type);
        $this->assertSame('Toko Emas Mulia Gold Palembang', $item->purchase_store);
        $this->assertSame('karat', $item->purity_source);
        $this->assertSame('17.0000', $item->purity_input);
        $this->assertSame('0.708333', (string) $item->purity_fraction);
        $this->assertSame('6.0800', (string) $item->net_weight_g);
        $this->assertSame('unknown', $item->stone_state);
        $this->assertSame('purchase', $item->acquisition_method);
        $this->assertSame('4895000.00', (string) $item->acquisition_cost);
        $this->assertSame('2023-04-24', $item->owned_since->format('Y-m-d'));
        $this->assertSame('exact', $item->owned_since_certainty);
        $this->assertSame('undecided', $item->purpose);
        $this->assertStringContainsString('Diimpor dari Excel (Rekap perhiasan.xlsx)', $item->notes);
        $this->assertNull(Item::where('name', 'Gelang Oval Glam')->first()); // left unchecked
    }

    public function test_a_row_that_needed_checking_keeps_the_original_purity_text_in_the_note(): void
    {
        $token = $this->preview($this->threeRows());

        $this->post("/koleksi/impor/{$token}", ['sheet' => 'Daftar Perhiasan', 'rows' => [5]]);

        $item = Item::where('name', 'Gelang Oval Glam')->firstOrFail();
        $this->assertSame('17.0000', $item->purity_input); // the karat wins
        $this->assertStringContainsString('Kadar di berkas: 750/17K', $item->notes);
    }

    public function test_the_browser_can_only_choose_row_numbers_not_change_what_a_row_contains(): void
    {
        $token = $this->preview($this->threeRows());

        $this->post("/koleksi/impor/{$token}", [
            'sheet' => 'Daftar Perhiasan', 'rows' => [4, 99, 1],
            // None of these may have any effect:
            'name' => 'DIUBAH', 'net_weight_g' => '9999', 'acquisition_cost' => '1', 'user_id' => User::factory()->create()->id, 'kind' => 'bar',
        ])->assertRedirect('/koleksi');

        $this->assertSame(1, Item::count()); // rows 99 and 1 do not exist in the preview
        $item = Item::firstOrFail();
        $this->assertSame('Gelang kaku putih', $item->name);
        $this->assertSame('6.0800', (string) $item->net_weight_g);
        $this->assertSame($this->user->id, $item->user_id);
        $this->assertSame('jewelry', $item->kind);
    }

    public function test_an_error_row_cannot_be_saved_even_if_its_number_is_sent(): void
    {
        $token = $this->preview([$this->jewelryRow(1, str_repeat('a', 161)), $this->jewelryRow(2, 'Cincin', date: '2024-01-02')]);

        $this->post("/koleksi/impor/{$token}", ['sheet' => 'Daftar Perhiasan', 'rows' => [4, 5]]);

        $this->assertSame(['Cincin'], Item::pluck('name')->all());
    }

    public function test_saving_with_nothing_checked_creates_nothing(): void
    {
        $token = $this->preview($this->threeRows());

        $this->post("/koleksi/impor/{$token}", ['sheet' => 'Daftar Perhiasan', 'rows' => []])->assertSessionHas('toast.type', 'error');
        $this->post("/koleksi/impor/{$token}", ['sheet' => 'Daftar Perhiasan'])->assertSessionHas('toast.type', 'error');

        $this->assertSame(0, Item::count());
        $this->get("/koleksi/impor/{$token}")->assertOk(); // the preview is still there
    }

    public function test_a_preview_can_be_saved_only_once(): void
    {
        $token = $this->preview($this->threeRows());

        $this->post("/koleksi/impor/{$token}", ['sheet' => 'Daftar Perhiasan', 'rows' => [4]]);
        $this->post("/koleksi/impor/{$token}", ['sheet' => 'Daftar Perhiasan', 'rows' => [4]])->assertNotFound();

        $this->assertSame(1, Item::count());
    }

    public function test_the_save_is_all_or_nothing(): void
    {
        $token = $this->preview($this->threeRows());

        $count = 0;
        Item::creating(function () use (&$count) {
            if (++$count === 3) {
                throw new \RuntimeException('boom');
            }
        });

        try {
            $this->withoutExceptionHandling();
            $this->post("/koleksi/impor/{$token}", ['sheet' => 'Daftar Perhiasan', 'rows' => [4, 5, 6]]);
            $this->fail('Expected the third row to fail');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        } finally {
            Item::getEventDispatcher()->forget('eloquent.creating: '.Item::class);
        }

        $this->assertSame(0, Item::count()); // the two rows before the failure were rolled back
        $this->assertNotNull(Cache::get("item-import:{$this->user->id}:{$token}")); // and the preview is kept to try again
    }

    // ---- duplicates -------------------------------------------------------------------------------------------

    public function test_importing_the_same_file_twice_marks_everything_as_a_duplicate_and_unchecked(): void
    {
        $token = $this->preview($this->threeRows());
        $this->post("/koleksi/impor/{$token}", ['sheet' => 'Daftar Perhiasan', 'rows' => [4, 5, 6]]);
        $this->assertSame(3, Item::count());

        $second = $this->preview($this->threeRows());

        $this->get("/koleksi/impor/{$second}")->assertInertia(fn (Assert $p) => $p
            ->where('counts', ['duplicate' => 3])
            ->where('rows', fn ($rows) => collect($rows)->every(fn ($r) => $r['selected'] === false && $r['status'] === 'duplicate')));

        // Saving only what is checked (nothing) adds nothing; checking one on purpose imports it.
        $this->post("/koleksi/impor/{$second}", ['sheet' => 'Daftar Perhiasan', 'rows' => [6]])->assertSessionHas('toast.message', '1 barang diimpor');
        $this->assertSame(4, Item::count());
    }

    public function test_a_row_that_appeared_in_the_collection_since_the_preview_is_skipped(): void
    {
        $token = $this->preview($this->threeRows());

        // The same piece is added by hand after the preview was made.
        Item::factory()->jewelry()->create([
            'user_id' => $this->user->id, 'name' => 'Gelang kaku putih', 'owned_since' => '2023-04-24',
            'net_weight_g' => '6.0800', 'acquisition_cost' => '4895000.00',
        ]);

        $this->post("/koleksi/impor/{$token}", ['sheet' => 'Daftar Perhiasan', 'rows' => [4, 6]])
            ->assertSessionHas('toast.message', '1 barang diimpor, 1 dilewati karena sudah ada');

        $this->assertSame(1, Item::where('name', 'Gelang kaku putih')->count());
        $this->assertSame(1, Item::where('name', 'Cincin pita')->count());
    }

    // ---- ownership of the preview -----------------------------------------------------------------------------

    public function test_another_owners_preview_token_finds_nothing(): void
    {
        $token = $this->preview($this->threeRows());
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get("/koleksi/impor/{$token}")->assertNotFound();
        $this->actingAs($intruder)->post("/koleksi/impor/{$token}", ['sheet' => 'Daftar Perhiasan', 'rows' => [4]])->assertNotFound();
        $this->actingAs($intruder)->delete("/koleksi/impor/{$token}");

        $this->assertSame(0, Item::count());
        $this->actingAs($this->user)->get("/koleksi/impor/{$token}")->assertOk(); // the owner's preview is untouched
    }

    public function test_a_made_up_or_malformed_token_is_not_found(): void
    {
        $this->get('/koleksi/impor/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/koleksi/impor/pendek')->assertNotFound();
        $this->get('/koleksi/impor/'.rawurlencode('../../etc/passwd'))->assertNotFound();
    }

    public function test_a_preview_expires_after_30_minutes(): void
    {
        $token = $this->preview($this->threeRows());

        $this->travel(29)->minutes();
        $this->get("/koleksi/impor/{$token}")->assertOk();

        $this->travel(2)->minutes();
        $this->get("/koleksi/impor/{$token}")->assertNotFound();
    }

    public function test_cancelling_discards_the_preview(): void
    {
        $token = $this->preview($this->threeRows());

        $this->delete("/koleksi/impor/{$token}")->assertRedirect('/koleksi/impor');

        $this->get("/koleksi/impor/{$token}")->assertNotFound();
        $this->assertSame(0, Item::count());
    }

    // ---- safety -----------------------------------------------------------------------------------------------

    public function test_formulas_in_the_file_are_never_evaluated_and_a_formula_name_is_skipped(): void
    {
        $token = $this->preview([
            $this->jewelryRow(1, '=HYPERLINK("http://jahat.example","klik")'),
            $this->jewelryRow(2, 'Cincin aman', cost: '=1+1', date: '2024-01-02'),
        ]);

        $this->get("/koleksi/impor/{$token}")->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.name', 'Cincin aman')->where('rows.0.acquisition_cost', null));
    }

    public function test_the_file_name_in_the_preview_and_note_is_cleaned(): void
    {
        $token = $this->preview($this->threeRows(), "C:\\Users\\x\\..\\rekap\x01 \"aneh\".xlsx");

        $this->get("/koleksi/impor/{$token}")->assertInertia(fn (Assert $p) => $p->where('filename', 'rekap "aneh".xlsx'));
    }

    // ---- afterwards -------------------------------------------------------------------------------------------

    public function test_an_imported_item_opens_in_the_edit_form_and_saves_unchanged(): void
    {
        $token = $this->preview($this->threeRows());
        $this->post("/koleksi/impor/{$token}", ['sheet' => 'Daftar Perhiasan', 'rows' => [4]]);
        $item = Item::firstOrFail();

        $this->get("/koleksi/{$item->id}/edit")->assertOk();
        $this->get("/koleksi/{$item->id}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('item.purchase_store', 'Toko Emas Mulia Gold Palembang')
            ->where('item.net_g', '6.0800')
            ->where('item.price_per_gram', '805098.68')
            ->where('item.status', 'complete'));

        $this->patch("/koleksi/{$item->id}", [
            'name' => $item->name, 'ownership' => 'own', 'jewelry_type' => 'gelang', 'acquisition_method' => 'purchase',
            'acquisition_cost' => '4895000', 'purchase_store' => $item->purchase_store, 'owned_since' => '2023-04-24',
            'owned_since_certainty' => 'exact', 'stone_state' => 'unknown', 'net_weight_g' => '6.08',
            'purity_source' => 'karat', 'purity_input' => '17', 'purpose' => 'undecided', 'notes' => $item->notes,
        ])->assertSessionHasNoErrors();
    }

    public function test_imported_pure_gold_follows_the_karat(): void
    {
        $token = $this->preview($this->threeRows());
        $this->post("/koleksi/impor/{$token}", ['sheet' => 'Daftar Perhiasan', 'rows' => [4]]);

        // 6.08 g at 17K: 6.08 x 17 / 24 = 4.3067 g of pure gold
        $this->get('/koleksi/'.Item::firstOrFail()->id)->assertInertia(fn (Assert $p) => $p->where('item.pure_gold_g', '4.3067'));
    }
}
