<?php

use App\Http\Middleware\RequirePermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['perm' => RequirePermission::class]);
        $middleware->appendToGroup('web', \App\Http\Middleware\SetBranch::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\SetBranch::class);
        $middleware->redirectGuestsTo(fn (Request $r) => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn (Request $r, Throwable $e) => $r->is('api/*') || $r->expectsJson());
    })->create();
