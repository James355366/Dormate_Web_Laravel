<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;

/** Room directory: Laravel controller -> Eloquent model -> Blade view. */
class RoomController extends PageController
{
    protected function data(Request $request, array $u): array
    {
        dm_allow_roles($u, ['RESIDENT', 'DORM_ADMIN']);
        $rows = Room::query()
            ->where('dorm_id', $u['dorm_id'])
            ->where('active', 1)
            ->where('room_number', 'like', '%'.trim((string) $request->query('q', '')).'%')
            ->withCount(['residents as occupied' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('room_number')->get()->toArray();

        return compact('rows');
    }
}
