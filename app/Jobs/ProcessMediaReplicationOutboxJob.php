<?php

namespace App\Jobs;

use App\Models\MediaReplicationOutbox;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessMediaReplicationOutboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        $replica = TenantStorageDisks::replicaPublicDisk();
        $mirror = TenantStorageDisks::publicMirrorDisk();

        for ($i = 0; $i < 25; $i++) {
            $row = DB::transaction(function () {
                $r = MediaReplicationOutbox::query()
                    ->whereIn('status', [
                        MediaReplicationOutbox::STATUS_PENDING,
                        MediaReplicationOutbox::STATUS_FAILED,
                    ])
                    ->where('available_at', '<=', now())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($r === null) {
                    return null;
                }

                $r->update([
                    'status' => MediaReplicationOutbox::STATUS_PROCESSING,
                    'attempts' => $r->attempts + 1,
                ]);

                return $r->fresh();
            });

            if ($row === null) {
                return;
            }

            try {
                if ($row->operation === MediaReplicationOutbox::OPERATION_DELETE) {
                    $replica->delete($row->object_key);
                } else {
                    $key = $row->object_key;
                    if (! $mirror->exists($key)) {
                        throw new \RuntimeException('Mirror object missing for replication: '.$key);
                    }
                    $body = $mirror->get($key);
                    if (! is_string($body)) {
                        throw new \RuntimeException('Could not read mirror object: '.$key);
                    }
                    $opts = TenantStorage::mergedOptionsForPublicObjectWrite($replica, []);
                    if (! $replica->put($key, $body, $opts)) {
                        throw new \RuntimeException('Replica put returned false for: '.$key);
                    }
                }

                $row->update([
                    'status' => MediaReplicationOutbox::STATUS_COMPLETED,
                    'last_error' => null,
                ]);
            } catch (Throwable $e) {
                report($e);
                $delay = min(3600, 30 * (2 ** min($row->attempts, 10)));
                $row->update([
                    'status' => MediaReplicationOutbox::STATUS_FAILED,
                    'last_error' => $e->getMessage(),
                    'available_at' => now()->addSeconds($delay),
                ]);
            }
        }
    }
}
