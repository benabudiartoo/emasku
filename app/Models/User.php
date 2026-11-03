<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'hide_amounts',
    ];

    // `is_admin` and `last_login_at` are deliberately not fillable: no request input can set them.

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'hide_amounts' => 'boolean',
            'is_admin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function zakatPeriods(): HasMany
    {
        return $this->hasMany(ZakatPeriod::class);
    }
}
