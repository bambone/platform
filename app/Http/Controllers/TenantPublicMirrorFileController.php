<?php

namespace App\Http\Controllers;

use App\Support\Storage\TenantPublicObjectKey;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dev-only fallback when nginx is not serving /media/ from the local mirror disk.
 */
class TenantPublicMirrorFileController extends Controller
{
    public function show(Request $request, string $path): Response
    {
        if (! app()->isLocal()) {
            abort(404);
        }

        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
            abort(404);
        }

        $tenant = currentTenant();
        abort_if($tenant === null, 403);

        try {
            $key = TenantPublicObjectKey::assertWebExposedTenantPublicKey($path, (int) $tenant->id);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        $disk = TenantStorageDisks::publicMirrorDisk();
        if (! $disk instanceof FilesystemAdapter || ! TenantStorageDisks::usesLocalFlyAdapter($disk)) {
            abort(503);
        }

        if (! $disk->exists($key)) {
            abort(404);
        }

        $absolute = $disk->path($key);
        if (! is_file($absolute)) {
            abort(404);
        }

        return response()->file($absolute);
    }
}
