<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OwnerIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $a;

    private User $b;

    private Item $itemB;

    private Location $locationB;

    private Attachment $photoB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->a = User::factory()->create();
        $this->b = User::factory()->create();
        $this->locationB = Location::factory()->create(['user_id' => $this->b->id, 'name' => 'Brankas B']);
        $this->itemB = Item::factory()->create(['user_id' => $this->b->id, 'location_id' => $this->locationB->id]);

        Storage::disk('local')->put('attachments/'.$this->b->id.'/foto-b.png', 'rahasia-b');
        $this->photoB = Attachment::forceCreate([
            'user_id' => $this->b->id,
            'item_id' => $this->itemB->id,
            'path' => 'attachments/'.$this->b->id.'/foto-b.png',
            'original_name' => 'sertifikat.png',
            'mime' => 'image/png',
            'size' => 9,
        ]);
    }

    public function test_guest_is_sent_to_login_for_owner_pages(): void
    {
        $this->get('/koleksi/'.$this->itemB->id)->assertRedirect('/login');
        $this->get('/lampiran/'.$this->photoB->id)->assertRedirect('/login');
    }

    // Acceptance case 8: user A cannot view, edit, delete, or download user B's data.
    public function test_user_a_cannot_view_edit_or_delete_user_b_item(): void
    {
        $this->actingAs($this->a);

        $this->get('/koleksi/'.$this->itemB->id)->assertNotFound();
        $this->get('/koleksi/'.$this->itemB->id.'/edit')->assertNotFound();
        $this->delete('/koleksi/'.$this->itemB->id)->assertNotFound();

        $this->assertDatabaseHas('items', ['id' => $this->itemB->id]);
        $this->assertFalse(Gate::forUser($this->a)->allows('update', $this->itemB));
        $this->assertFalse(Gate::forUser($this->a)->allows('view', $this->itemB));
    }

    public function test_user_a_cannot_download_delete_or_upload_to_user_b_photo(): void
    {
        $this->actingAs($this->a);

        $this->get('/lampiran/'.$this->photoB->id)->assertNotFound();
        $this->delete('/lampiran/'.$this->photoB->id)->assertNotFound();
        $this->post('/koleksi/'.$this->itemB->id.'/lampiran', [
            'photo' => UploadedFile::fake()->image('x.png'),
        ])->assertNotFound();

        $this->assertDatabaseCount('attachments', 1);
        Storage::disk('local')->assertExists($this->photoB->path);
    }

    public function test_owner_uploads_and_downloads_own_photo_with_private_headers(): void
    {
        $item = Item::factory()->create(['user_id' => $this->a->id]);
        $this->actingAs($this->a);

        $this->post('/koleksi/'.$item->id.'/lampiran', ['photo' => UploadedFile::fake()->image('cincin.png')])
            ->assertRedirect();

        $photo = Attachment::where('item_id', $item->id)->firstOrFail();
        $this->assertSame($this->a->id, $photo->user_id);
        $this->assertStringStartsWith('attachments/'.$this->a->id.'/', $photo->path);
        $this->assertNotSame('cincin.png', basename($photo->path)); // random stored name
        Storage::disk('local')->assertExists($photo->path);

        $response = $this->get('/lampiran/'.$photo->id);
        $response->assertOk();
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_upload_rejects_non_images_and_oversize(): void
    {
        $item = Item::factory()->create(['user_id' => $this->a->id]);
        $this->actingAs($this->a);

        $this->post('/koleksi/'.$item->id.'/lampiran', ['photo' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('photo');
        $this->post('/koleksi/'.$item->id.'/lampiran', ['photo' => UploadedFile::fake()->image('besar.png')->size(6000)])
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('attachments', 1); // only B's fixture
    }

    public function test_attachments_live_on_private_disk_not_in_public_root(): void
    {
        $this->assertNotSame(realpath(public_path()), realpath(config('filesystems.disks.local.root')));
        $this->assertStringNotContainsString(
            str_replace('\\', '/', public_path()),
            str_replace('\\', '/', (string) config('filesystems.disks.local.root')),
        );
        $this->assertArrayNotHasKey('url', config('filesystems.disks.local')); // no public URL for the private disk
        $this->assertFileDoesNotExist(public_path($this->photoB->path));
    }
}
