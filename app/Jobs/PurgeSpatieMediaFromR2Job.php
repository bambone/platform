<?php

namespace App\Jobs;

use App\Support\Storage\TenantPublicMediaWriter;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class PurgeSpatieMediaFromR2Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $tenantId,
        private readonly int $mediaId,
    ) {}

    public function handle(TenantPublicMediaWriter $writer): void
    {
        $replica = TenantStorageDisks::replicaPublicDisk();
        $prefix = 'tenants/'.$this->tenantId.'/public/media/'.$this->mediaId;
        try {
            $keys = $replica->allFiles($prefix);
        } catch (Throwable) {
            return;
        }

        foreach ($keys as $key) {
            $writer->deleteReplicaKeyOrOutbox($this->tenantId, $key);
        }
    }
}
