<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dorm extends Model
{
    public $timestamps = false;

    protected $table = 'dorms';

    protected $guarded = ['id'];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function residents()
    {
        return $this->hasMany(User::class)->where('role', 'RESIDENT');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
