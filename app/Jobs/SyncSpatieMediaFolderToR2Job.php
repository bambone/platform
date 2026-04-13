<?php

namespace App\Jobs;

use App\Tenant\StorageQuota\TenantMediaStorageQuotaObserver;
use App\Support\Storage\TenantPublicMediaWriter;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class SyncSpatieMediaFolderToR2Job implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 120;

    public function __construct(
        private readonly int $mediaId,
    ) {}

    public function uniqueId(): string
    {
        return 'sync-spatie-media-r2-'.$this->mediaId;
    }

    public function handle(TenantPublicMediaWriter $writer): void
    {
        $media = Media::query()->find($this->mediaId);
        if ($media === null) {
            return;
        }
        if (! TenantMediaStorageQuotaObserver::shouldApply($media)) {
            return;
        }
        $tid = TenantMediaStorageQuotaObserver::tenantId($media);
        if ($tid === null) {
            return;
        }

        $mirror = TenantStorageDisks::publicMirrorDisk();
        $prefix = 'tenants/'.$tid.'/public/media/'.$media->getKey();
        try {
            $keys = $mirror->allFiles($prefix);
        } catch (Throwable) {
            return;
        }

        foreach ($keys as $key) {
            $writer->replicateMirrorKeyToReplicaOrOutbox($tid, $key);
        }
    }
}
