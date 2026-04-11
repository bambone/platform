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
     * Local disk: {@code response()->file()}. Cloud (R2/S3): изображения и видео стримятся через приложение
     * с явным {@code Content-Type} (избегаем {@code ERR_BLOCKED_BY_ORB} при неверных метаданных на CDN);
     * прочие типы — HTTP 302 на канонический URL объекта.
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

        if ($this->shouldStreamThroughOrigin($path)) {
            if (! $disk->exists($relative)) {
                abort(404);
            }
            $stream = $disk->readStream($relative);
            if (! is_resource($stream)) {
                abort(404);
            }
            $mime = $disk->mimeType($relative);
            $mime = is_string($mime) && $mime !== '' ? $mime : $this->mimeFromFilename($path);
            if ($this->shouldOverrideCloudMime($mime, $path)) {
                $mime = $this->mimeFromFilename($path);
            }

            return response()->stream(function () use ($stream): void {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, 200, [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        return redirect()->away($disk->url($relative), 302);
    }

    private function shouldStreamThroughOrigin(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'mp4', 'webm', 'mov'], true);
    }

    private function shouldOverrideCloudMime(string $mime, string $path): bool
    {
        if (in_array($mime, ['application/octet-stream', 'binary/octet-stream', 'application/x-www-form-urlencoded'], true)) {
            return true;
        }
        if (str_starts_with($mime, 'text/') || str_contains($mime, 'html') || str_contains($mime, 'xml')) {
            return true;
        }

        return false;
    }

    private function mimeFromFilename(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'avif' => 'image/avif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            default => 'application/octet-stream',
        };
    }
}
