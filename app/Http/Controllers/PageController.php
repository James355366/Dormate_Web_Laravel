<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use PDOException;
use RuntimeException;
use Throwable;

class PageController extends Controller
{
    public function show(Request $request)
    {
        $page = $request->route('page', 'home');
        try {
            if ($page === 'home') {
                return response()->view('dormate.landing');
            }
            dm_db();
            $u = dm_current_user();
            if (in_array($page, ['login', 'register', 'forgot', 'reset'], true)) {
                if ($u && in_array($page, ['login', 'register'], true)) {
                    return redirect()->route('dormate.dashboard');
                }

                return response()->view('dormate.auth', compact('page'));
            }
            if (! $u) {
                return redirect()->route('dormate.login');
            }
            if ($u['status'] === 'pending') {
                return response()->view('dormate.pending', compact('u'));
            }
            if ($page === 'pending') {
                return redirect()->route('dormate.dashboard');
            }
            $data = $this->data($request, $u) + compact('u', 'page');

            return response()->view('dormate.'.$page, $data);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Blade wraps exceptions; preserve the intended permission response.
            while ($e->getPrevious()) {
                $e = $e->getPrevious();
            }
            if ($e instanceof PDOException || $e instanceof QueryException) {
                report($e);

                return response()->view('errors.setup', [], 503);
            }
            $status = $e instanceof RuntimeException ? 403 : 500;
            if ($status === 500) {
                report($e);
            }

            return response()->view('errors.dormate', [
                'status' => $status,
                'message' => $status === 403 ? $e->getMessage() : 'This page could not be loaded. Please try again.',
            ], $status);
        }
    }

    protected function data(Request $request, array $u): array
    {
        return [];
    }
}
