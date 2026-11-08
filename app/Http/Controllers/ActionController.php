<?php

namespace App\Http\Controllers;

use App\Services\DormateActions;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use PDOException;
use RuntimeException;
use Throwable;

class ActionController extends Controller
{
    public function store(Request $request, DormateActions $actions)
    {
        $page = $request->route('page', 'home');
        if ($request->input('action') === 'set_theme') {
            $request->validate(['theme' => ['required', 'in:light,dark']]);

            return redirect()->to(dm_theme_return_url(), 303)
                ->withCookie(cookie('dormate_theme', $request->input('theme'), 60 * 24 * 180, '/', null, $request->isSecure(), true, false, 'lax'));
        }
        try {
            $actions->handle();

            return redirect()->route('dormate.dashboard');
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (PDOException|QueryException $e) {
            report($e);
            dm_flash((string) $e->getCode() === '23000' ? 'This record already exists or conflicts with an existing record.' : 'The change could not be saved. Check MySQL and try again.', 'danger');
            $status = 400;
        } catch (RuntimeException $e) {
            dm_flash($e->getMessage(), 'danger');
            $status = 400;
        } catch (Throwable $e) {
            report($e);
            dm_flash('Something went wrong. Please try again.', 'danger');
            $status = 500;
        }
        $controller = $page === 'rooms' ? RoomController::class : PageController::class;
        $response = app($controller)->show($request);
        if (! $response->isRedirection() && $response->getStatusCode() < 400) {
            $response->setStatusCode($status);
        }

        return $response;
    }
}
