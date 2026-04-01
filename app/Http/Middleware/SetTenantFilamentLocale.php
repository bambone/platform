<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Кабинет клиента (Filament admin) — русский UI для встроенных строк пакетов
 * (кнопки «Создать», таблицы, модалки), пока APP_LOCALE=en для остального приложения.
 */
final class SetTenantFilamentLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $previous = app()->getLocale();
        app()->setLocale('ru');

        try {
            return $next($request);
        } finally {
            app()->setLocale($previous);
        }
    }
}
