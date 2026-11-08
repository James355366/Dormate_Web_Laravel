<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    public $timestamps = false;

    protected $table = 'rooms';

    protected $guarded = ['id'];

    public function dorm()
    {
        return $this->belongsTo(Dorm::class);
    }

    public function residents()
    {
        return $this->hasMany(User::class)->where('role', 'RESIDENT');
    }
}
