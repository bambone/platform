<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Use the incoming request scheme + host as the URL generator root so absolute
 * URLs (e.g. Livewire update URI) stay same-origin when APP_URL targets another
 * host (apex marketing vs platform subdomain).
 *
 * Also align the public storage disk base URL with the same origin. Otherwise
 * Spatie / Filament FileUpload builds media URLs from APP_URL (e.g. http://rentbase.local/storage/…)
 * while the admin runs on https://tenant.host — the browser blocks fetch (mixed content) and
 * FilePond stays on "Waiting for size" / Loading.
 */
class UseRequestOriginForUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->getSchemeAndHttpHost();

        URL::useOrigin($origin);

        config([
            'filesystems.disks.public.url' => rtrim($origin, '/').'/storage',
        ]);

        return $next($request);
    }
}
