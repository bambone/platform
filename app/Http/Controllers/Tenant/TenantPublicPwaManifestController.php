<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantPublicPwaManifestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = tenant();
        if ($tenant === null) {
            abort(404);
        }

        $push = $tenant->pushSettings;
        if ($push === null || ! $push->is_pwa_enabled) {
            abort(404);
        }

        $name = $push->pwa_name ?: $tenant->defaultPublicSiteName();
        $shortName = $push->pwa_short_name ?: mb_substr($name, 0, 12);
        $startUrl = $push->pwa_start_url ?: '/';
        $theme = $push->pwa_theme_color ?: '#0c0c0e';
        $bg = $push->pwa_background_color ?: '#0c0c0e';
        $display = $push->pwa_display ?: 'standalone';
        $icons = $push->pwa_icons_json;
        if (! is_array($icons) || $icons === []) {
            $icons = [
                [
                    'src' => '/favicon.ico',
                    'sizes' => 'any',
                    'type' => 'image/x-icon',
                    'purpose' => 'any',
                ],
            ];
        }

        $id = $tenant->id ? '/?pwa_id='.$tenant->id : '/';

        $payload = [
            'id' => $id,
            'name' => $name,
            'short_name' => $shortName,
            'start_url' => $startUrl,
            'display' => $display,
            'theme_color' => $theme,
            'background_color' => $bg,
            'icons' => $icons,
        ];

        return response()
            ->json($payload, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
