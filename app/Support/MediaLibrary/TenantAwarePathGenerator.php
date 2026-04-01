<?php

namespace App\Support\MediaLibrary;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

/**
 * Канонически: {@code tenants/{id}/public/media/{media_id}/}; цепочка fallback — в {@see TenantMediaStoragePaths::resolveBasePathForExistingFile()}.
 */
class TenantAwarePathGenerator extends DefaultPathGenerator
{
    protected function getBasePath(Media $media): string
    {
        return TenantMediaStoragePaths::resolveBasePathForExistingFile($media);
    }
}
