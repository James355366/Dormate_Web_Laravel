<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    public $timestamps = false;

    protected $table = 'bills';

    protected $guarded = ['id'];

    public function resident()
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function dorm()
    {
        return $this->belongsTo(Dorm::class);
    }
}
