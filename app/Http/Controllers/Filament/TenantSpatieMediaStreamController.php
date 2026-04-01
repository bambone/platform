<?php

namespace App\Http\Controllers\Filament;

use App\Http\Controllers\Controller;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * Отдаёт файл Spatie Media с хоста админки (same-origin), чтобы превью Filament FileUpload не упиралось в CORS R2/CDN.
 */
final class TenantSpatieMediaStreamController extends Controller
{
    public function show(Request $request, Media $media): Response
    {
        $this->assertMediaAccessibleForCurrentTenant($media);

        $conversion = (string) $request->query('conversion', '');
        if ($conversion !== '' && ! $media->hasGeneratedConversion($conversion)) {
            $conversion = '';
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($media->disk);
        $relative = $media->getPathRelativeToRoot($conversion);

        if (TenantStorageDisks::usesLocalFlyAdapter($disk) && ! $disk->exists($relative)) {
            abort(404);
        }

        $filename = $media->file_name;
        $mime = $media->mime_type ?: null;

        return $disk->response($relative, $filename, array_filter([
            'Content-Type' => $mime,
        ]));
    }

    private function assertMediaAccessibleForCurrentTenant(Media $media): void
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            abort(403);
        }

        $media->loadMissing('model');
        $model = $media->model;
        if ($model === null) {
            abort(404);
        }

        if (in_array(BelongsToTenant::class, class_uses_recursive($model), true)) {
            if ((int) $model->getAttribute('tenant_id') !== (int) $tenant->id) {
                abort(403);
            }
        }
    }

}
