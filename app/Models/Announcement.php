<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    public $timestamps = false;

    protected $table = 'announcements';

    protected $guarded = ['id'];

    public function scopeVisibleTo(Builder $query, array $user): Builder
    {
        if ($user['role'] === 'SUPER_ADMIN') {
            return $query;
        }
        if (empty($user['dorm_id'])) {
            return $query->whereRaw('1 = 0');
        }

        // Match both the dorm and its administrator; global/foreign posts stay private.
        return $query->where('announcements.dorm_id', $user['dorm_id'])
            ->whereHas('dorm', fn (Builder $dorm) => $dorm
                ->whereColumn('dorms.owner_id', 'announcements.posted_by'));
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function dorm()
    {
        return $this->belongsTo(Dorm::class);
    }
}
