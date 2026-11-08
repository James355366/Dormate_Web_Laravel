<?php

namespace App\Http\Controllers;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaController extends Controller
{
    public function show(Request $request)
    {
        try {
            $u = dm_require_user();
            $id = (int) $request->query('id');
            $kind = $request->query('kind');
            $path = null;
            if ($kind === 'avatar') {
                $r = dm_one('SELECT id,dorm_id,avatar_path FROM users WHERE id=?', [$id]);
                if ($r && ((int) $r['id'] === (int) $u['id'] || $u['role'] === 'SUPER_ADMIN' || ($u['dorm_id'] && (int) $r['dorm_id'] === (int) $u['dorm_id']))) {
                    $path = $r['avatar_path'];
                }
            } elseif ($u['status'] === 'active') {
                if ($kind === 'receipt') {
                    $path = dm_get_bill($u, $id)['receipt_path'];
                } elseif ($kind === 'report') {
                    $path = dm_get_report($u, $id)['photo_path'];
                } elseif ($kind === 'qr' && ($id === (int) $u['dorm_id'] || $u['role'] === 'SUPER_ADMIN')) {
                    $path = dm_one('SELECT gcash_qr FROM dorms WHERE id=?', [$id])['gcash_qr'] ?? null;
                }
            }
            if (! $path || ! preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/D', $path)) {
                abort(404);
            }
            $file = Storage::disk('local')->path('uploads/'.$path);
            if (! is_file($file)) {
                abort(404);
            }

            return response()->file($file, ['Cache-Control' => 'private, no-store']);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            return response('Image unavailable.', 404);
        }
    }
}
