<?php

namespace App\Services\Seo;

use App\Models\TenantSeoFile;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Support\Facades\Storage;

/**
 * Reads a published snapshot only when DB state and storage are consistent.
 */
final class TenantSeoSnapshotReader
{
    public function readValid(int $tenantId, string $type): ?string
    {
        $row = TenantSeoFile::query()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->first();

        if ($row === null || ! $row->exists || $row->storage_path === null || $row->storage_path === '') {
            return null;
        }

        $recordDisk = (string) ($row->storage_disk ?? '');
        $diskName = $recordDisk !== '' ? $recordDisk : TenantStorageDisks::privateDiskName();
        $disk = Storage::disk($diskName);

        if (! $disk->exists($row->storage_path)) {
            return null;
        }

        $content = $disk->get($row->storage_path);

        if (! is_string($content) || $content === '') {
            return null;
        }

        return $content;
    }
}
