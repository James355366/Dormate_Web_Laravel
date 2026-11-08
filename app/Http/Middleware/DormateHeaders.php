<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DormateHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $scripts = in_array($request->route('page'), ['login', 'register'], true) ? "'self'" : "'none'";
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src {$scripts}; style-src 'self' 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'self'");
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
