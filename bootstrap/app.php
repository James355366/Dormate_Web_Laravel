<?php

use App\Http\Middleware\DormateHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [DormateHeaders::class]);
        // Preserve blank optional fields in the original database schema.
        $middleware->remove(ConvertEmptyStringsToNull::class);
        $middleware->trimStrings(except: ['current_password', 'password', 'password_confirm']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
