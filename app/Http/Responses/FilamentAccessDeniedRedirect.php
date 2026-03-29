<?php

namespace App\Http\Responses;

use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Мягкая реакция на отказ политики в Filament: не показывать голую страницу «403».
 */
final class FilamentAccessDeniedRedirect
{
    public const SESSION_KEY = 'filament_access_denied_message';

    public static function message(): string
    {
        return 'Недостаточно прав для этой страницы или действия. Открыта главная страница панели.';
    }

    public static function tryRedirect(Request $request): ?RedirectResponse
    {
        $name = $request->route()?->getName();
        if (! is_string($name) || ! str_starts_with($name, 'filament.')) {
            return null;
        }

        if (! preg_match('/^filament\.([^.]+)\./', $name, $matches)) {
            return null;
        }

        try {
            $panel = Filament::getPanel($matches[1]);
        } catch (\Throwable) {
            return null;
        }

        return redirect()->to($panel->getUrl())->with(self::SESSION_KEY, self::message());
    }
}
