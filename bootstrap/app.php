<?php

require_once __DIR__.'/../app/helpers.php';

use App\Http\Middleware\RedirectMiddleware;
use App\Http\Middleware\ResolveTenantFromDomain;
use App\Http\Responses\FilamentAccessDeniedRedirect;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            ResolveTenantFromDomain::class,
            RedirectMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return FilamentAccessDeniedRedirect::tryRedirect($request);
        });
    })->create();
