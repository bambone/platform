<?php

namespace App\Support\Storage;

use App\Jobs\ProcessMediaReplicationOutboxJob;
use App\Models\MediaReplicationOutbox;
use App\Models\Tenant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Dual / local / r2 public writes with atomic local mirror and R2 replication outbox.
 */
final class TenantPublicMediaWriter
{
    public function __construct(
        private readonly EffectiveTenantMediaModeResolver $modes,
    ) {}

    /**
     * @param  array<string, mixed>  $options  Passed to cloud put (Cache-Control, visibility, …)
     */
    public function putPublicObjectKey(int $tenantId, string $objectKey, mixed $contents, array $options = []): bool
    {
        $key = TenantPublicObjectKey::normalize($objectKey);
        $this->assertTenantOwnsPublicKey($tenantId, $key);

        $writeMode = $this->modes->effectiveWriteMode($this->findTenant($tenantId));

        $mirrorName = TenantStorageDisks::publicMirrorDiskName();
        $replicaName = TenantStorageDisks::replicaPublicDiskName();
        $mirror = TenantStorageDisks::publicMirrorDisk();
        $replica = TenantStorageDisks::replicaPublicDisk();

        if ($writeMode === MediaWriteMode::R2Only) {
            return $this->putCloudOnly($replica, $key, $contents, $options);
        }

        $ok = $this->putMirrorAtomic($mirror, $key, $contents);
        if (! $ok) {
            return false;
        }

        if ($writeMode === MediaWriteMode::LocalOnly) {
            return true;
        }

        if ($mirrorName === $replicaName) {
            return true;
        }

        $cloudOptions = TenantStorage::mergedOptionsForPublicObjectWrite($replica, $options);
        try {
            if ($replica->put($key, $contents, $cloudOptions)) {
                return true;
            }
            $this->enqueueOutbox(MediaReplicationOutbox::OPERATION_PUT, $key, $tenantId, null, 'put returned false');
        } catch (Throwable $e) {
            report($e);
            $this->enqueueOutbox(MediaReplicationOutbox::OPERATION_PUT, $key, $tenantId, null, $e->getMessage());
        }

        ProcessMediaReplicationOutboxJob::dispatch()->afterCommit();

        return true;
    }

    public function deletePublicObjectKey(int $tenantId, string $objectKey): bool
    {
        $key = TenantPublicObjectKey::normalize($objectKey);
        $this->assertTenantOwnsPublicKey($tenantId, $key);

        $writeMode = $this->modes->effectiveWriteMode($this->findTenant($tenantId));

        $mirrorName = TenantStorageDisks::publicMirrorDiskName();
        $replicaName = TenantStorageDisks::replicaPublicDiskName();
        $mirror = TenantStorageDisks::publicMirrorDisk();
        $replica = TenantStorageDisks::replicaPublicDisk();

        if ($writeMode === MediaWriteMode::R2Only) {
            try {
                return $replica->delete($key);
            } catch (Throwable $e) {
                report($e);
                $this->enqueueOutbox(MediaReplicationOutbox::OPERATION_DELETE, $key, $tenantId, null, $e->getMessage());
                ProcessMediaReplicationOutboxJob::dispatch()->afterCommit();

                return false;
            }
        }

        try {
            $mirror->delete($key);
        } catch (Throwable $e) {
            report($e);
        }

        if ($writeMode === MediaWriteMode::LocalOnly) {
            return true;
        }

        if ($mirrorName === $replicaName) {
            return true;
        }

        try {
            if ($replica->delete($key)) {
                return true;
            }
            $this->enqueueOutbox(MediaReplicationOutbox::OPERATION_DELETE, $key, $tenantId, null, 'delete returned false');
        } catch (Throwable $e) {
            report($e);
            $this->enqueueOutbox(MediaReplicationOutbox::OPERATION_DELETE, $key, $tenantId, null, $e->getMessage());
        }

        ProcessMediaReplicationOutboxJob::dispatch()->afterCommit();

        return true;
    }

    /**
     * Spatie / backfill: mirror already has bytes; push to R2 when write mode is dual.
     */
    public function replicateMirrorKeyToReplicaOrOutbox(int $tenantId, string $objectKey): void
    {
        $key = TenantPublicObjectKey::normalize($objectKey);
        $this->assertTenantOwnsPublicKey($tenantId, $key);

        if ($this->modes->effectiveWriteMode($this->findTenant($tenantId)) !== MediaWriteMode::Dual) {
            return;
        }

        $mirrorName = TenantStorageDisks::publicMirrorDiskName();
        $replicaName = TenantStorageDisks::replicaPublicDiskName();
        if ($mirrorName === $replicaName) {
            return;
        }

        $mirror = TenantStorageDisks::publicMirrorDisk();
        $replica = TenantStorageDisks::replicaPublicDisk();
        if (! $mirror->exists($key)) {
            return;
        }
        $body = $mirror->get($key);
        if (! is_string($body)) {
            return;
        }

        $cloudOptions = TenantStorage::mergedOptionsForPublicObjectWrite($replica, []);
        try {
            if ($replica->put($key, $body, $cloudOptions)) {
                return;
            }
            $this->enqueueOutbox(MediaReplicationOutbox::OPERATION_PUT, $key, $tenantId, null, 'replica put returned false');
        } catch (Throwable $e) {
            report($e);
            $this->enqueueOutbox(MediaReplicationOutbox::OPERATION_PUT, $key, $tenantId, null, $e->getMessage());
        }

        ProcessMediaReplicationOutboxJob::dispatch()->afterCommit();
    }

    /**
     * After Spatie removed files from mirror: drop objects on replica only (dual / r2_only).
     */
    public function deleteReplicaKeyOrOutbox(int $tenantId, string $objectKey): void
    {
        $key = TenantPublicObjectKey::normalize($objectKey);
        $this->assertTenantOwnsPublicKey($tenantId, $key);

        $writeMode = $this->modes->effectiveWriteMode($this->findTenant($tenantId));
        if ($writeMode === MediaWriteMode::LocalOnly) {
            return;
        }

        $mirrorName = TenantStorageDisks::publicMirrorDiskName();
        $replicaName = TenantStorageDisks::replicaPublicDiskName();
        if ($mirrorName === $replicaName && $writeMode === MediaWriteMode::Dual) {
            return;
        }

        $replica = TenantStorageDisks::replicaPublicDisk();
        try {
            if ($replica->delete($key)) {
                return;
            }
            $this->enqueueOutbox(MediaReplicationOutbox::OPERATION_DELETE, $key, $tenantId, null, 'replica delete returned false');
        } catch (Throwable $e) {
            report($e);
            $this->enqueueOutbox(MediaReplicationOutbox::OPERATION_DELETE, $key, $tenantId, null, $e->getMessage());
        }

        ProcessMediaReplicationOutboxJob::dispatch()->afterCommit();
    }

    /**
     * @param  array<string, mixed>|null  $payloadJson
     */
    public function enqueueOutbox(string $operation, string $objectKey, ?int $tenantId, ?array $payloadJson, string $errorHint): void
    {
        MediaReplicationOutbox::query()->create([
            'operation' => $operation,
            'object_key' => $objectKey,
            'tenant_id' => $tenantId,
            'status' => MediaReplicationOutbox::STATUS_PENDING,
            'attempts' => 0,
            'last_error' => $errorHint,
            'available_at' => now(),
            'payload_json' => $payloadJson,
        ]);
    }

    private function assertTenantOwnsPublicKey(int $tenantId, string $key): void
    {
        $prefix = 'tenants/'.$tenantId.'/public/';
        if (! str_starts_with($key, $prefix)) {
            throw new InvalidArgumentException('Object key is not under the tenant public prefix.');
        }
    }

    private function putCloudOnly(Filesystem $disk, string $key, mixed $contents, array $options): bool
    {
        $cloudOptions = TenantStorage::mergedOptionsForPublicObjectWrite($disk, $options);

        return $disk->put($key, $contents, $cloudOptions);
    }

    private function putMirrorAtomic(Filesystem $disk, string $key, mixed $contents): bool
    {
        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('Public mirror disk must use FilesystemAdapter.');
        }
        if (TenantStorageDisks::usesLocalFlyAdapter($disk)) {
            $fullPath = $disk->path($key);
            $dir = dirname($fullPath);
            if (! is_dir($dir)) {
                if (! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                    return false;
                }
            }
            $tmp = $dir.'/'.basename($fullPath).'.tmp.'.bin2hex(random_bytes(8));
            try {
                if ($contents instanceof UploadedFile) {
                    $real = $contents->getRealPath();
                    if ($real === false || ! @copy($real, $tmp)) {
                        return false;
                    }
                } elseif ($contents instanceof \SplFileInfo) {
                    $src = $contents->getPathname();
                    if (! @copy($src, $tmp)) {
                        return false;
                    }
                } elseif (is_resource($contents)) {
                    $fh = fopen($tmp, 'wb');
                    if ($fh === false) {
                        return false;
                    }
                    stream_copy_to_stream($contents, $fh);
                    fclose($fh);
                } else {
                    $written = file_put_contents($tmp, $contents);
                    if ($written === false) {
                        return false;
                    }
                }
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
                if (! @rename($tmp, $fullPath)) {
                    @unlink($tmp);

                    return false;
                }

                return true;
            } catch (Throwable $e) {
                @unlink($tmp);
                throw $e;
            }
        }

        $tmpKey = $key.'.tmp.'.bin2hex(random_bytes(8));
        if (! $disk->put($tmpKey, $contents)) {
            return false;
        }
        if ($disk->exists($key)) {
            $disk->delete($key);
        }

        return $disk->move($tmpKey, $key);
    }

    private function findTenant(int $tenantId): ?Tenant
    {
        try {
            return Tenant::query()->find($tenantId);
        } catch (QueryException) {
            return null;
        }
    }
}
