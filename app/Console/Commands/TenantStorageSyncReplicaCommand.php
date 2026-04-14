<?php

namespace App\Console\Commands;

use App\Support\Storage\TenantStorage;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Двусторонняя догонка ключей между локальным зеркалом и R2 без удалений.
 * Команда выравнивает отсутствующие объекты и при необходимости разрешает
 * конфликты только по размеру. Побайтовую идентичность при совпадении размера
 * не гарантирует.
 * Удаление объектов — только осознанно (через приложение или tenant-storage:delete-public-key).
 *
 * @see docs/operations/r2-tenant-storage.md
 */
class TenantStorageSyncReplicaCommand extends Command
{
    protected $signature = 'tenant-storage:sync-replica
                            {--dry-run : Только план, без записи}
                            {--tenant= : Ограничить префиксом tenants/N/ (N = id тенанта)}
                            {--scope=public : public|private|both}
                            {--left-public= : Левый публичный диск (по умолчанию: tenant_storage.public_mirror_disk)}
                            {--right-public= : Правый публичный диск (по умолчанию: tenant_storage.replica_public_disk)}
                            {--left-private= : Левый приватный диск (по умолчанию: local)}
                            {--right-private= : Правый приватный диск (по умолчанию: r2-private)}
                            {--prefix= : Префикс ключей (по умолчанию: tenants/ или tenants/N/ при --tenant)}
                            {--on-conflict=skip : skip|prefer-left|prefer-right — при разном размере на обеих сторонах}';

    protected $description = 'Догоняет отсутствующие объекты между локальным зеркалом и R2 без удалений; при --on-conflict разрешает только конфликты размера. Совпадение размера не означает побайтовую идентичность.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $scope = strtolower((string) $this->option('scope'));
        if (! in_array($scope, ['public', 'private', 'both'], true)) {
            $this->error('Invalid --scope (use public, private, or both).');

            return self::FAILURE;
        }

        $conflict = strtolower((string) $this->option('on-conflict'));
        if (! in_array($conflict, ['skip', 'prefer-left', 'prefer-right'], true)) {
            $this->error('Invalid --on-conflict.');

            return self::FAILURE;
        }

        $tenantOpt = $this->option('tenant');
        $tenantId = null;
        if ($tenantOpt !== null && $tenantOpt !== '') {
            $tenantRaw = trim((string) $tenantOpt);
            if (! ctype_digit($tenantRaw) || (int) $tenantRaw <= 0) {
                $this->error('Invalid --tenant. Expected a positive integer.');

                return self::FAILURE;
            }
            $tenantId = (int) $tenantRaw;
        }

        $prefixOverride = $this->option('prefix');
        $basePrefix = $prefixOverride !== null && (string) $prefixOverride !== ''
            ? ltrim((string) $prefixOverride, '/')
            : ($tenantId !== null ? 'tenants/'.$tenantId.'/' : 'tenants/');

        return TenantStorageQuotaService::withoutQuotaEnforcement(function () use ($scope, $dry, $conflict, $basePrefix): int {
            $ok = true;
            if ($scope === 'public' || $scope === 'both') {
                $left = (string) ($this->option('left-public') ?: config('tenant_storage.public_mirror_disk', 'tenant-public-mirror'));
                $right = (string) ($this->option('right-public') ?: config('tenant_storage.replica_public_disk', 'r2-public'));
                if ($this->syncPair('public', $left, $right, $basePrefix, $dry, $conflict) !== self::SUCCESS) {
                    $ok = false;
                }
            }
            if ($scope === 'private' || $scope === 'both') {
                $left = (string) ($this->option('left-private') ?: 'local');
                $right = (string) ($this->option('right-private') ?: 'r2-private');
                if ($this->syncPair('private', $left, $right, $basePrefix, $dry, $conflict) !== self::SUCCESS) {
                    $ok = false;
                }
            }

            return $ok ? self::SUCCESS : self::FAILURE;
        });
    }

    private function syncPair(string $label, string $leftName, string $rightName, string $prefix, bool $dry, string $conflictMode): int
    {
        foreach (['left' => $leftName, 'right' => $rightName] as $side => $disk) {
            if ($disk === '' || config("filesystems.disks.{$disk}") === null) {
                $this->error("[{$label}] Unknown disk: {$disk}");

                return self::FAILURE;
            }
        }

        if ($leftName === $rightName) {
            $this->warn("[{$label}] Source and target disks are identical ({$leftName}); skipped.");

            return self::SUCCESS;
        }

        $left = Storage::disk($leftName);
        $right = Storage::disk($rightName);

        $this->info("[{$label}] Sync {$leftName} <-> {$rightName} prefix \"{$prefix}\"".($dry ? ' (dry-run)' : ''));

        $leftKeys = $this->listKeys($left, $leftName, $prefix);
        if ($leftKeys === null) {
            $this->error("[{$label}] Unable to enumerate/stat source side {$leftName}; sync aborted.");

            return self::FAILURE;
        }

        $rightKeys = $this->listKeys($right, $rightName, $prefix);
        if ($rightKeys === null) {
            $this->error("[{$label}] Unable to enumerate/stat source side {$rightName}; sync aborted.");

            return self::FAILURE;
        }

        $allKeys = array_unique(array_merge(array_keys($leftKeys), array_keys($rightKeys)));
        sort($allKeys);

        $pushed = 0;
        $pulled = 0;
        $skipped = 0;
        $conflicts = 0;
        $failed = 0;

        foreach ($allKeys as $key) {
            $l = $leftKeys[$key] ?? null;
            $r = $rightKeys[$key] ?? null;

            if ($l !== null && $r !== null) {
                if ($l === $r) {
                    $skipped++;

                    continue;
                }

                $conflicts++;
                $action = match ($conflictMode) {
                    'prefer-left' => 'push',
                    'prefer-right' => 'pull',
                    default => 'skip',
                };

                if ($action === 'skip') {
                    $this->warn("[{$label}] conflict size left={$l} right={$r} {$key} (skipped)");

                    continue;
                }

                if ($dry) {
                    $this->line("[{$label}] [dry] conflict resolve {$action} {$key}");

                    continue;
                }

                if ($action === 'push') {
                    if ($this->copyStream($left, $right, $leftName, $rightName, $key)) {
                        $pushed++;
                    } else {
                        $failed++;
                    }
                } elseif ($this->copyStream($right, $left, $rightName, $leftName, $key)) {
                    $pulled++;
                } else {
                    $failed++;
                }

                continue;
            }

            if ($l !== null) {
                if ($dry) {
                    $this->line("[{$label}] [dry] push {$key}");
                    $pushed++;

                    continue;
                }
                if ($this->copyStream($left, $right, $leftName, $rightName, $key)) {
                    $pushed++;
                } else {
                    $failed++;
                }

                continue;
            }

            if ($r !== null) {
                if ($dry) {
                    $this->line("[{$label}] [dry] pull {$key}");
                    $pulled++;

                    continue;
                }
                if ($this->copyStream($right, $left, $rightName, $leftName, $key)) {
                    $pulled++;
                } else {
                    $failed++;
                }

                continue;
            }
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['keys_total', (string) count($allKeys)],
                ['pushed_to_right', (string) $pushed],
                ['pulled_to_left', (string) $pulled],
                ['skipped_same_size', (string) $skipped],
                ['size_conflicts_seen', (string) $conflicts],
                ['failed', (string) $failed],
            ]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, int>|null key => size; null when listing/stat fails
     */
    private function listKeys(Filesystem $disk, string $diskName, string $prefix): ?array
    {
        $dir = rtrim(str_replace('\\', '/', $prefix), '/');

        $out = [];
        try {
            $files = $dir === '' ? $disk->allFiles() : $disk->allFiles($dir);
        } catch (Throwable $e) {
            $this->error("[list] {$diskName}::{$dir} failed: ".$e->getMessage());

            return null;
        }

        foreach ($files as $key) {
            $key = ltrim(str_replace('\\', '/', (string) $key), '/');

            if ($key === '' || str_ends_with($key, '/')) {
                continue;
            }

            try {
                $out[$key] = (int) $disk->size($key);
            } catch (Throwable $e) {
                $this->error("[stat] {$diskName}::{$key} failed: ".$e->getMessage());

                return null;
            }
        }

        return $out;
    }

    private function copyStream(
        Filesystem $from,
        Filesystem $to,
        string $fromName,
        string $toName,
        string $key,
    ): bool {
        try {
            if (! $from->exists($key)) {
                return false;
            }
            $stream = $from->readStream($key);
            if ($stream === false) {
                $this->error("readStream failed {$fromName}::{$key}");

                return false;
            }

            $dir = dirname($key);
            if ($dir !== '.' && $dir !== '' && ! $to->exists($dir)) {
                $to->makeDirectory($dir);
            }

            $options = $this->writeOptionsForDestination($toName, $to);

            if (! $to instanceof FilesystemAdapter) {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                return false;
            }
            $ok = $to->writeStream($key, $stream, $options);
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (! $ok) {
                $this->error("writeStream failed {$toName}::{$key}");

                return false;
            }
            $this->line("copied → {$toName} {$key}");

            return true;
        } catch (Throwable $e) {
            $this->error("copy {$key}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function writeOptionsForDestination(string $toDiskName, Filesystem $to): array
    {
        if (config("filesystems.disks.{$toDiskName}.driver") !== 's3') {
            return [];
        }

        $replicaPublic = (string) config('tenant_storage.replica_public_disk', 'r2-public');
        if ($toDiskName === $replicaPublic) {
            return TenantStorage::mergedOptionsForPublicObjectWrite($to, []);
        }

        return [];
    }
}
