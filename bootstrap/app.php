<?php

require_once __DIR__.'/../app/helpers.php';

use App\Http\Middleware\RedirectMiddleware;
use App\Http\Middleware\RedirectWwwTenantToCanonicalPublicUrl;
use App\Http\Middleware\ResolveTenantFromDomain;
use App\Http\Middleware\UseRequestOriginForUrls;
use App\Http\Responses\FilamentAccessDeniedRedirect;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // withMiddleware() is invoked for ConsoleKernel before `config` is bound (artisan) — do not call config() there.
        if (app()->bound('config')) {
            $telegramWebhook = ltrim((string) config('telegram.webhook_path', 'webhooks/telegram'), '/');
            if ($telegramWebhook !== '') {
                $middleware->validateCsrfTokens(except: [$telegramWebhook]);
            }
        }

        $middleware->append(UseRequestOriginForUrls::class);

        $middleware->web(prepend: [
            ResolveTenantFromDomain::class,
            RedirectWwwTenantToCanonicalPublicUrl::class,
            RedirectMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return FilamentAccessDeniedRedirect::tryRedirect($request);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }
            if (tenant() === null) {
                return null;
            }

            return response()->view('tenant.errors.404', [], 404);
        });
    })->create();
