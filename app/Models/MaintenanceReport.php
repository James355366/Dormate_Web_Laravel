<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceReport extends Model
{
    public $timestamps = false;

    protected $table = 'maintenance_reports';

    protected $guarded = ['id'];

    public function resident()
    {
        return $this->belongsTo(User::class, 'resident_id');
    }

    public function assignedStaff()
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
