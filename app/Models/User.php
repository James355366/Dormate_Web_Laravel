<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['password_hash'];

    protected $casts = ['session_version' => 'integer', 'dorm_id' => 'integer', 'room_id' => 'integer'];

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function dorm(): BelongsTo
    {
        return $this->belongsTo(Dorm::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
