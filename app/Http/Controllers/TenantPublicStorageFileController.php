<?php

namespace App\Http\Controllers;

use App\Support\Storage\TenantStorageDisks;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TenantPublicStorageFileController extends Controller
{
    /**
     * Tenant public files under {@code tenants/{id}/public/…} on the configured public disk.
     *
     * Local disk: {@code response()->file()}. Cloud public disk (R2/S3): HTTP 302 to canonical object URL.
     * Route stays under /storage/tenants/… for same-origin bookmarks; cloud mode redirects to CDN.
     */
    public function show(Request $request, string $tenantId, string $path): Response
    {
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
            abort(404);
        }

        $tenant = currentTenant();
        abort_if($tenant === null || (int) $tenantId !== (int) $tenant->id, 403);

        $relative = "tenants/{$tenantId}/public/{$path}";
        $diskName = TenantStorageDisks::publicDiskName();
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);

        if (TenantStorageDisks::usesLocalFlyAdapter($disk)) {
            if (! $disk->exists($relative)) {
                abort(404);
            }
            $absolute = $disk->path($relative);
            if (! is_file($absolute)) {
                abort(404);
            }

            return response()->file($absolute);
        }

        // R2/S3: skip exists() (remote HEAD); CDN returns 404 if key missing.
        return redirect()->away($disk->url($relative), 302);
    }
}
