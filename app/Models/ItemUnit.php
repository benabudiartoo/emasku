<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemUnit extends Model
{
    use HasUlids;

    protected $fillable = ['serial_number', 'quantity'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
