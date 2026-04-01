<?php

namespace App\Http\Controllers;

use App\Themes\ThemeRegistry;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Отдаёт файлы платформенной темы из {@code resources/themes/{key}/public/…} без копирования в {@code public/}.
 */
class ThemePlatformAssetController extends Controller
{
    public function show(ThemeRegistry $registry, string $theme, string $path): BinaryFileResponse
    {
        $theme = strtolower($theme);
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/', $theme)) {
            abort(404);
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $def = $registry->get($theme);
        if ($def->key !== $theme) {
            abort(404);
        }

        $full = resource_path('themes/'.$theme.'/public/'.$path);
        if (! is_file($full)) {
            abort(404);
        }

        $mime = File::mimeType($full) ?: 'application/octet-stream';

        return response()->file($full, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
