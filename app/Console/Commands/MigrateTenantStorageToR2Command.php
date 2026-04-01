<?php

namespace App\Console\Commands;

use App\Models\Motorcycle;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\TenantSeoFile;
use App\Models\TenantSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Copy tenant files from local disks to R2 (or any configured from/to pair). Idempotent; v1 never deletes source.
 *
 * @see docs/operations/r2-tenant-storage.md
 */
class MigrateTenantStorageToR2Command extends Command
{
    protected $signature = 'tenant-storage:migrate-to-r2
                            {--dry-run : Show planned actions only}
                            {--tenant= : Limit to a single tenant id}
                            {--only= : Comma list: branding,media,seo,site (default: all; site = tenants/*/public/site template hero/video)}
                            {--from-public=public : Source public disk}
                            {--to-public=r2-public : Target public disk}
                            {--from-private=local : Source private disk}
                            {--to-private=r2-private : Target private disk}';

    protected $description = 'Copy tenant branding/media/SEO keys to R2 (or any from/to disks): idempotent, no source delete. Before TENANT_STORAGE_PUBLIC_DISK and MEDIA_DISK point at the target public disk, run branding for ALL tenants (omit --tenant); a single-tenant pilot is not enough — tenant_branding_asset_url() requires files on the configured public disk.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $tenantFilter = $this->option('tenant');
        $tenantId = $tenantFilter !== null && $tenantFilter !== '' ? (int) $tenantFilter : null;

        $onlyRaw = (string) ($this->option('only') ?? '');
        $only = $onlyRaw === ''
            ? ['branding', 'media', 'seo', 'site']
            : array_values(array_filter(array_map('trim', explode(',', $onlyRaw))));

        if ($tenantId !== null && in_array('branding', $only, true)) {
            $this->warn('Branding scope is limited to one tenant (--tenant). Before pointing TENANT_STORAGE_PUBLIC_DISK (and MEDIA_DISK) at the target public disk, run branding migration for all tenants without --tenant; keep dry-run and full-wave logs as rollout artifacts.');
        }

        $fromPublic = (string) $this->option('from-public');
        $toPublic = (string) $this->option('to-public');
        $fromPrivate = (string) $this->option('from-private');
        $toPrivate = (string) $this->option('to-private');

        foreach (['from-public' => $fromPublic, 'to-public' => $toPublic, 'from-private' => $fromPrivate, 'to-private' => $toPrivate] as $label => $disk) {
            if ($disk === '' || config("filesystems.disks.{$disk}") === null) {
                $this->error("Unknown disk for --{$label}={$disk}");

                return self::FAILURE;
            }
        }

        if ($fromPublic === $toPublic && $fromPrivate === $toPrivate) {
            $this->warn('Source and target public (and private) disks are identical; nothing to migrate.');

            return self::SUCCESS;
        }

        if (in_array('branding', $only, true) && $fromPublic !== $toPublic) {
            $this->migrateBranding($fromPublic, $toPublic, $tenantId, $dry);
        }

        if (in_array('media', $only, true) && $fromPublic !== $toPublic) {
            $this->migrateMedia($fromPublic, $toPublic, $tenantId, $dry);
        }

        if (in_array('seo', $only, true) && $fromPrivate !== $toPrivate) {
            $this->migrateSeo($fromPrivate, $toPrivate, $tenantId, $dry);
        }

        if (in_array('site', $only, true) && $fromPublic !== $toPublic) {
            $this->migrateTenantPublicSite($fromPublic, $toPublic, $tenantId, $dry);
        }

        return self::SUCCESS;
    }

    private function logLine(string $status, array $ctx): void
    {
        $this->line(json_encode(array_merge(['status' => $status], $ctx), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function migrateBranding(string $fromDisk, string $toDisk, ?int $tenantId, bool $dry): void
    {
        $keys = ['logo_path', 'favicon_path', 'hero_path'];
        $q = TenantSetting::query()->where('group', 'branding')->whereIn('key', $keys)->where('value', '!=', '');
        if ($tenantId !== null) {
            $q->where('tenant_id', $tenantId);
        }

        $from = Storage::disk($fromDisk);
        $to = Storage::disk($toDisk);

        foreach ($q->cursor() as $row) {
            /** @var TenantSetting $row */
            $path = ltrim((string) $row->value, '/');
            if ($path === '') {
                continue;
            }

            $ctx = [
                'scope' => 'branding',
                'old_disk' => $fromDisk,
                'new_disk' => $toDisk,
                'old_key' => $path,
                'new_key' => $path,
                'db' => 'tenant_settings',
                'row_id' => $row->id,
                'tenant_id' => $row->tenant_id,
            ];

            if (! $from->exists($path)) {
                $this->logLine('skipped_missing_source', $ctx);

                continue;
            }

            if ($to->exists($path) && (int) $to->size($path) === (int) $from->size($path)) {
                $this->logLine('skipped_already_migrated', $ctx);

                continue;
            }

            if ($dry) {
                $this->logLine('dry_run_would_copy', $ctx);

                continue;
            }

            $stream = $from->readStream($path);
            if ($stream === false) {
                $this->logLine('error_read', $ctx);

                continue;
            }

            $ok = $to->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $ok) {
                $this->logLine('error_write', $ctx);

                continue;
            }

            $this->logLine('copied', $ctx);
        }
    }

    /**
     * Шаблонные публичные файлы лендинга (hero poster, видео) под {@code tenants/{id}/public/site/…}.
     */
    private function migrateTenantPublicSite(string $fromDisk, string $toDisk, ?int $tenantId, bool $dry): void
    {
        $from = Storage::disk($fromDisk);
        $to = Storage::disk($toDisk);

        $q = Tenant::query()->orderBy('id');
        if ($tenantId !== null) {
            $q->where('id', $tenantId);
        }

        foreach ($q->cursor() as $tenant) {
            $base = 'tenants/'.$tenant->id.'/public/site';
            try {
                $files = $from->allFiles($base);
            } catch (\Throwable) {
                $files = [];
            }

            foreach ($files as $rel) {
                $rel = ltrim((string) $rel, '/');
                if ($rel === '') {
                    continue;
                }

                $ctx = [
                    'scope' => 'site',
                    'old_disk' => $fromDisk,
                    'new_disk' => $toDisk,
                    'old_key' => $rel,
                    'new_key' => $rel,
                    'tenant_id' => $tenant->id,
                ];

                if (! $from->exists($rel)) {
                    $this->logLine('skipped_missing_source', $ctx);

                    continue;
                }

                if ($to->exists($rel) && (int) $to->size($rel) === (int) $from->size($rel)) {
                    $this->logLine('skipped_already_migrated', $ctx);

                    continue;
                }

                if ($dry) {
                    $this->logLine('dry_run_would_copy', $ctx);

                    continue;
                }

                $stream = $from->readStream($rel);
                if ($stream === false) {
                    $this->logLine('error_read', $ctx);

                    continue;
                }

                $ok = $to->writeStream($rel, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (! $ok) {
                    $this->logLine('error_write', $ctx);

                    continue;
                }

                $this->logLine('copied', $ctx);
            }
        }
    }

    private function migrateMedia(string $fromDisk, string $toDisk, ?int $tenantId, bool $dry): void
    {
        $q = Media::query()->where('disk', $fromDisk);
        if ($tenantId !== null) {
            $q->where(function ($w) use ($tenantId): void {
                $w->where(function ($w2) use ($tenantId): void {
                    $w2->where('model_type', Motorcycle::class)
                        ->whereIn('model_id', Motorcycle::query()->where('tenant_id', $tenantId)->select('id'));
                })->orWhere(function ($w2) use ($tenantId): void {
                    $w2->where('model_type', Review::class)
                        ->whereIn('model_id', Review::query()->where('tenant_id', $tenantId)->select('id'));
                });
            });
        }

        $from = Storage::disk($fromDisk);
        $to = Storage::disk($toDisk);

        foreach ($q->cursor() as $media) {
            /** @var Media $media */
            $folder = dirname($media->getPathRelativeToRoot());
            if ($folder === '.' || $folder === '') {
                $folder = '';
            }

            $files = $folder === '' ? [$media->getPathRelativeToRoot()] : $from->allFiles($folder);

            $ctxBase = [
                'scope' => 'media',
                'old_disk' => $fromDisk,
                'new_disk' => $toDisk,
                'media_id' => $media->id,
                'model_type' => $media->model_type,
                'model_id' => $media->model_id,
            ];

            if ($media->disk === $toDisk) {
                $this->logLine('skipped_already_migrated', $ctxBase + ['old_key' => $media->getPathRelativeToRoot()]);

                continue;
            }

            $allOk = true;
            foreach ($files as $rel) {
                $rel = ltrim($rel, '/');
                if (! $from->exists($rel)) {
                    $this->logLine('skipped_missing_source', $ctxBase + ['old_key' => $rel]);
                    $allOk = false;

                    break;
                }
                if ($to->exists($rel) && (int) $to->size($rel) === (int) $from->size($rel)) {
                    continue;
                }
                if ($dry) {
                    $this->logLine('dry_run_would_copy', $ctxBase + ['old_key' => $rel, 'new_key' => $rel]);

                    continue;
                }
                $stream = $from->readStream($rel);
                if ($stream === false) {
                    $this->logLine('error_read', $ctxBase + ['old_key' => $rel]);
                    $allOk = false;

                    break;
                }
                $ok = $to->writeStream($rel, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if (! $ok) {
                    $this->logLine('error_write', $ctxBase + ['old_key' => $rel]);
                    $allOk = false;

                    break;
                }
                $this->logLine('copied', $ctxBase + ['old_key' => $rel, 'new_key' => $rel]);
            }

            if ($dry) {
                continue;
            }

            if ($allOk) {
                $convDisk = $media->conversions_disk ?: $media->disk;
                $newConv = $convDisk === $fromDisk ? $toDisk : $convDisk;

                DB::table('media')->where('id', $media->id)->update([
                    'disk' => $toDisk,
                    'conversions_disk' => $newConv,
                    'updated_at' => now(),
                ]);
                $this->logLine('db_updated', $ctxBase + ['disk' => $toDisk, 'conversions_disk' => $newConv]);
            }
        }
    }

    private function migrateSeo(string $fromDisk, string $toDisk, ?int $tenantId, bool $dry): void
    {
        $q = TenantSeoFile::query();
        if ($tenantId !== null) {
            $q->where('tenant_id', $tenantId);
        }

        $to = Storage::disk($toDisk);

        foreach ($q->cursor() as $row) {
            /** @var TenantSeoFile $row */
            $recordDisk = (string) ($row->storage_disk ?? '');
            if ($recordDisk === $toDisk) {
                $this->logLine('skipped_already_migrated', [
                    'scope' => 'seo',
                    'row_id' => $row->id,
                    'tenant_id' => $row->tenant_id,
                    'storage_disk' => $recordDisk,
                ]);

                continue;
            }

            if ($recordDisk !== '' && $recordDisk !== $fromDisk) {
                $this->logLine('skipped_wrong_source_disk', [
                    'scope' => 'seo',
                    'row_id' => $row->id,
                    'tenant_id' => $row->tenant_id,
                    'storage_disk' => $recordDisk,
                ]);

                continue;
            }

            if (trim((string) $row->storage_path) === '' && trim((string) $row->backup_storage_path) === '') {
                continue;
            }

            $src = $recordDisk !== '' ? Storage::disk($recordDisk) : Storage::disk($fromDisk);
            $pathsOk = true;

            foreach (['storage_path' => $row->storage_path, 'backup_storage_path' => $row->backup_storage_path] as $field => $path) {
                $path = ltrim((string) $path, '/');
                if ($path === '') {
                    continue;
                }

                $ctx = [
                    'scope' => 'seo',
                    'old_disk' => $recordDisk !== '' ? $recordDisk : $fromDisk,
                    'new_disk' => $toDisk,
                    'old_key' => $path,
                    'new_key' => $path,
                    'db' => 'tenant_seo_files',
                    'row_id' => $row->id,
                    'tenant_id' => $row->tenant_id,
                    'field' => $field,
                ];

                if (! $src->exists($path)) {
                    $this->logLine('skipped_missing_source', $ctx);

                    continue;
                }

                if ($to->exists($path) && (int) $to->size($path) === (int) $src->size($path)) {
                    $this->logLine('skipped_already_migrated', $ctx);

                    continue;
                }

                if ($dry) {
                    $this->logLine('dry_run_would_copy', $ctx);

                    continue;
                }

                $stream = $src->readStream($path);
                if ($stream === false) {
                    $this->logLine('error_read', $ctx);
                    $pathsOk = false;

                    break;
                }

                $ok = $to->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (! $ok) {
                    $this->logLine('error_write', $ctx);
                    $pathsOk = false;

                    break;
                }

                $this->logLine('copied', $ctx);
            }

            if (! $dry && $pathsOk) {
                TenantSeoFile::query()->whereKey($row->id)->update(['storage_disk' => $toDisk]);
                $this->logLine('db_updated', ['scope' => 'seo', 'row_id' => $row->id, 'storage_disk' => $toDisk]);
            }
        }
    }
}
