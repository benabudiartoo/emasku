<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;

class Item extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'owned_since' => 'date:Y-m-d',
            'haul_start' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ItemUnit::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /** Store an uploaded photo on the private disk. The owner is set from the caller, never from input. */
    public function attachPhoto(UploadedFile $file, string $userId): Attachment
    {
        $path = $file->store("attachments/{$userId}", 'local'); // random hashed name

        $attachment = new Attachment([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
        $attachment->user_id = $userId;
        $this->attachments()->save($attachment);

        return $attachment;
    }
}
