<?php

namespace App\Console\Commands;

use App\Models\Motorcycle;
use App\Models\Tenant;
use App\Models\TenantSeoFile;
use App\Models\TenantSeoFileGeneration;
use App\Models\TenantSetting;
use App\Support\MediaLibrary\TenantMediaStoragePaths;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class TenantNormalizeStorageCommand extends Command
{
    protected $signature = 'tenant:normalize-storage
                            {--dry-run : Показать план без изменений на диске и в БД}
                            {--skip-media : Не переносить Spatie media}
                            {--skip-seo : Не трогать SEO-снимки на диске local}
                            {--skip-branding : Не переносить каталоги брендинга на public}
                            {--link-bikes : Привязать файлы из bikes/ к мотоциклам без обложки}
                            {--seed-theme-assets : Скопировать hero-видео/постер шаблона moto в tenants/{id}/public/site/… для тенантов с theme_key пусто/moto/default}';

    protected $description = 'Включает сегменты public/private под tenants/{id}; переносит Spatie media с диска local (private) на диск медиа; обновляет пути в БД.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->normalizeLog('=== tenant:normalize-storage start dry='.($dry ? '1' : '0'));

        if ($dry) {
            $this->warn('Режим dry-run: файлы и БД не меняются.');
        }

        try {
            if (! $this->option('skip-media')) {
                $this->repairMisnestedSiteUnderPublicMedia($dry);
                $this->relocateSpatieMedia($dry);
                $this->alignMisplacedSpatieMediaFilesWithRecords($dry);
            }
            if (! $this->option('skip-branding')) {
                $this->relocateBrandingIntoPublicSegment($dry);
                $this->rewriteBrandingPathsInDatabase($dry);
            }
            if (! $this->option('skip-seo')) {
                $this->relocateSeoIntoPrivateSegment($dry);
                $this->rewriteSeoPathsInDatabase($dry);
            }
            if ($this->option('link-bikes')) {
                $this->linkBikesFolderToMotorcycles($dry);
            }
            if ($this->option('seed-theme-assets') && ! $dry) {
                $this->seedMotoTemplateThemeAssets();
            }
            $this->removeLegacySystemArchiveFolder($dry);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Готово.');
        $this->normalizeLog('=== tenant:normalize-storage done');

        return self::SUCCESS;
    }

    private function normalizeLog(string $message): void
    {
        $path = storage_path('logs/tenant-normalize.log');
        $line = '['.now()->toIso8601String().'] '.$message.PHP_EOL;
        File::append($path, $line);
    }

    private function relocateSpatieMedia(bool $dry): void
    {
        $diskName = (string) config('media-library.disk_name', config('filesystems.default'));
        $disk = Storage::disk($diskName);
        $this->info("Spatie media (диск [{$diskName}])…");

        $count = 0;
        $skippedAlready = 0;
        $privateDisk = Storage::disk('local');

        Media::query()->orderBy('id')->lazyById(100)->each(function (Media $media) use ($disk, $privateDisk, $dry, &$count, &$skippedAlready): void {
            $media->loadMissing('model');
            if ($media->model === null) {
                return;
            }

            $targetBase = TenantMediaStoragePaths::canonicalPublicMediaBase($media);
            $targetFile = $targetBase.'/'.$media->file_name;

            if ($disk->exists($targetFile)) {
                $skippedAlready++;

                return;
            }

            $sources = array_filter([
                TenantMediaStoragePaths::legacyFlatBasePath($media),
                TenantMediaStoragePaths::previousTenantFlatModelFolder($media),
                TenantMediaStoragePaths::legacyTenantMediaWithoutPublicSegment($media),
            ]);

            $movedOnMediaDisk = false;
            foreach ($sources as $fromBase) {
                if ($fromBase === $targetBase) {
                    continue;
                }
                if ($disk->exists($fromBase.'/'.$media->file_name)) {
                    $this->line("  media #{$media->id}: {$fromBase} → {$targetBase}");
                    $this->normalizeLog("media #{$media->id}: move {$fromBase} → {$targetBase}");
                    $count++;
                    if (! $dry) {
                        $this->moveDirectoryTree($disk, $fromBase, $targetBase);
                    }
                    $movedOnMediaDisk = true;

                    break;
                }
            }

            if ($movedOnMediaDisk || $disk->exists($targetFile)) {
                return;
            }

            $this->relocateMediaFromPrivateAppDisk($media, $disk, $privateDisk, $targetBase, $dry, $count);
        });

        $this->normalizeLog("Spatie media: moved_trees={$count} skipped_already_present={$skippedAlready} dry=".($dry ? '1' : '0'));
        $this->info($dry ? "  (dry-run) переносов media: {$count}" : "  перенесено деревьев media: {$count}");
    }

    /**
     * Маркетинг/брендинг иногда попадают в {@code tenants/{id}/public/media/site/…} вместо {@code …/public/site/…} — переносим.
     */
    private function repairMisnestedSiteUnderPublicMedia(bool $dry): void
    {
        $disk = Storage::disk(TenantStorageDisks::publicDiskName());
        $this->info('Исправление вложения public/media/site → public/site…');

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $tenantId = (int) $tenantId;
            $ts = TenantStorage::forTrusted($tenantId);
            $wrong = $ts->publicPath('media/site');
            $right = $ts->publicPath('site');
            if (! $disk->exists($wrong) || ! $this->directoryHasFiles($disk, $wrong)) {
                continue;
            }

            $this->line("  tenant {$tenantId}: {$wrong} → {$right}");
            $this->normalizeLog("tenant {$tenantId}: repair media/site → site");
            if (! $dry) {
                if ($disk->exists($right)) {
                    $this->mergeDirectoryInto($disk, $wrong, $right);
                    $disk->deleteDirectory($wrong);
                } else {
                    $disk->makeDirectory(dirname($right));
                    $disk->move($wrong, $right);
                }
            }
        }
    }

    /**
     * Файл лежит в {@code tenants/{tid}/public/media/{другой_id}/}, а запись Spatie указывает на свой id — копируем в канонический каталог.
     */
    private function alignMisplacedSpatieMediaFilesWithRecords(bool $dry): void
    {
        $diskName = (string) config('media-library.disk_name', 'public');
        $disk = Storage::disk($diskName);
        $this->info('Выравнивание файлов Spatie по id (чужая папка media/{id})…');

        $fixed = 0;
        Media::query()->orderBy('id')->lazyById(100)->each(function (Media $media) use ($disk, $diskName, $dry, &$fixed): void {
            $media->loadMissing('model');
            if ($media->model === null) {
                return;
            }
            if (TenantMediaStoragePaths::tenantSegment($media) === '_unscoped') {
                return;
            }
            if (! in_array($media->disk, ['public', 'local'], true)) {
                return;
            }

            $tid = (int) TenantMediaStoragePaths::tenantSegment($media);
            $canonicalBase = TenantMediaStoragePaths::canonicalPublicMediaBase($media);
            $canonicalFile = $canonicalBase.'/'.$media->file_name;

            if ($disk->exists($canonicalFile)) {
                return;
            }

            $searchPrefix = 'tenants/'.$tid.'/public/media/';
            if (! $disk->exists($searchPrefix)) {
                return;
            }

            $candidates = [];
            foreach ($disk->allFiles($searchPrefix) as $path) {
                if (str_contains($path, '/conversions/')) {
                    continue;
                }
                if (basename($path) !== $media->file_name) {
                    continue;
                }
                if (! preg_match('#^'.preg_quote($searchPrefix, '#').'(\d+)/#', $path)) {
                    continue;
                }
                if (str_starts_with($path, $canonicalBase.'/')) {
                    continue;
                }
                $candidates[] = $path;
            }

            if (count($candidates) !== 1) {
                return;
            }

            $from = $candidates[0];
            $fixed++;
            $this->line("  media #{$media->id}: misplaced {$from} → {$canonicalFile}");
            $this->normalizeLog("media #{$media->id}: align_misplaced {$from} → {$canonicalFile}");
            if (! $dry) {
                if (! $disk->exists($canonicalBase)) {
                    $disk->makeDirectory($canonicalBase);
                }
                $disk->put($canonicalFile, $disk->get($from));
                $disk->delete($from);
                if ($media->disk === 'local') {
                    $media->forceFill([
                        'disk' => $diskName,
                        'conversions_disk' => $diskName,
                    ])->saveQuietly();
                }
            }
        });

        $this->normalizeLog("Spatie media align_misplaced: fixed_files={$fixed} dry=".($dry ? '1' : '0'));
        $this->info($dry ? "  (dry-run) выровнено media-файлов: {$fixed}" : "  выровнено media-файлов: {$fixed}");
    }

    /**
     * Файлы иногда оказываются на диске {@code local} ({@code storage/app/private/…}), тогда как в БД {@code disk=public}
     * и URL строится как {@code /storage/...} — в браузере 403/404. Копируем дерево в канонический путь на диске медиа.
     */
    private function relocateMediaFromPrivateAppDisk(
        Media $media,
        Filesystem $mediaDisk,
        Filesystem $privateDisk,
        string $targetBase,
        bool $dry,
        int &$count,
    ): void {
        $targetFile = $targetBase.'/'.$media->file_name;
        if ($mediaDisk->exists($targetFile)) {
            return;
        }

        if (! in_array($media->disk, ['public', 'local'], true)) {
            return;
        }

        $sources = array_filter([
            TenantMediaStoragePaths::legacyFlatBasePath($media),
            TenantMediaStoragePaths::previousTenantFlatModelFolder($media),
            TenantMediaStoragePaths::legacyTenantMediaWithoutPublicSegment($media),
        ]);

        foreach ($sources as $fromBase) {
            if ($fromBase === '' || $fromBase === $targetBase) {
                continue;
            }
            $candidate = $fromBase.'/'.$media->file_name;
            if (! $privateDisk->exists($candidate)) {
                continue;
            }

            $this->line("  media #{$media->id}: private[local] {$fromBase}/ → {$targetBase} (disk was {$media->disk})");
            $this->normalizeLog("media #{$media->id}: private→media_disk {$fromBase} → {$targetBase}");
            $count++;
            if (! $dry) {
                $this->copyDirectoryTreeAcrossDisks($privateDisk, $fromBase, $mediaDisk, $targetBase, true);
                if ($media->disk === 'local') {
                    $media->forceFill([
                        'disk' => (string) config('media-library.disk_name', 'public'),
                        'conversions_disk' => (string) config('media-library.disk_name', 'public'),
                    ])->saveQuietly();
                }
            }

            return;
        }
    }

    private function copyDirectoryTreeAcrossDisks(
        Filesystem $fromDisk,
        string $fromBase,
        Filesystem $toDisk,
        string $toBase,
        bool $deleteSourceAfter,
    ): void {
        if (! $fromDisk->exists($fromBase)) {
            return;
        }

        $files = $fromDisk->allFiles($fromBase);
        foreach ($files as $path) {
            $suffix = substr($path, strlen($fromBase) + 1);
            $dest = $toBase.'/'.ltrim($suffix, '/');
            $dir = dirname($dest);
            if (! $toDisk->exists($dir)) {
                $toDisk->makeDirectory($dir);
            }
            $toDisk->put($dest, $fromDisk->get($path));
        }

        if ($deleteSourceAfter) {
            $fromDisk->deleteDirectory($fromBase);
        }
    }

    private function moveDirectoryTree(Filesystem $disk, string $fromBase, string $toBase): void
    {
        if (! $disk->exists($fromBase)) {
            return;
        }

        $files = $disk->allFiles($fromBase);
        foreach ($files as $path) {
            $suffix = substr($path, strlen($fromBase) + 1);
            $dest = $toBase.'/'.$suffix;
            $dir = dirname($dest);
            if (! $disk->exists($dir)) {
                $disk->makeDirectory($dir);
            }
            $disk->move($path, $dest);
        }

        $disk->deleteDirectory($fromBase);
    }

    private function relocateBrandingIntoPublicSegment(bool $dry): void
    {
        $disk = Storage::disk(TenantStorageDisks::publicDiskName());
        $this->info('Брендинг → tenants/{id}/public/site/…');

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $tenantId = (int) $tenantId;
            $ts = TenantStorage::forTrusted($tenantId);
            $r = $ts->root();
            $pairs = [
                [$r.'/logo', $ts->publicPath('site/logo')],
                [$r.'/favicon', $ts->publicPath('site/favicon')],
                [$r.'/hero', $ts->publicPath('site/hero')],
                [$r.'/site/logo', $ts->publicPath('site/logo')],
                [$r.'/site/favicon', $ts->publicPath('site/favicon')],
                [$r.'/site/hero', $ts->publicPath('site/hero')],
                [$ts->publicPath('media/site'), $ts->publicPath('site')],
            ];
            foreach ($pairs as [$old, $new]) {
                if ($disk->exists($old) && $this->directoryHasFiles($disk, $old)) {
                    $this->line("  tenant {$tenantId}: {$old} → {$new}");
                    if (! $dry) {
                        if ($disk->exists($new)) {
                            $this->mergeDirectoryInto($disk, $old, $new);
                            $disk->deleteDirectory($old);
                        } else {
                            $disk->makeDirectory(dirname($new));
                            $disk->move($old, $new);
                        }
                    }
                }
            }
        }
    }

    private function directoryHasFiles(Filesystem $disk, string $dir): bool
    {
        return $disk->exists($dir) && $disk->allFiles($dir) !== [];
    }

    private function mergeDirectoryInto(Filesystem $disk, string $from, string $to): void
    {
        foreach ($disk->allFiles($from) as $path) {
            $suffix = substr($path, strlen($from) + 1);
            $dest = $to.'/'.$suffix;
            $disk->makeDirectory(dirname($dest));
            if ($disk->exists($dest)) {
                $disk->delete($dest);
            }
            $disk->move($path, $dest);
        }
        $disk->deleteDirectory($from);
    }

    private function rewriteBrandingPathsInDatabase(bool $dry): void
    {
        $this->info('Обновление путей branding.*_path в tenant_settings…');

        $keys = ['logo_path', 'favicon_path', 'hero_path'];

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $tenantId = (int) $tenantId;
            $ts = TenantStorage::forTrusted($tenantId);
            foreach ($keys as $key) {
                $setting = TenantSetting::query()
                    ->where('tenant_id', $tenantId)
                    ->where('group', 'branding')
                    ->where('key', $key)
                    ->first();
                if ($setting === null || $setting->value === '' || $setting->value === null) {
                    continue;
                }
                $val = str_replace('\\', '/', trim((string) $setting->value));
                $newVal = str_replace($ts->root().'/public/media/site', $ts->publicPath('site'), $val);
                $newVal = str_replace($ts->publicPath('media/site'), $ts->publicPath('site'), $newVal);
                $newVal = str_replace($ts->root().'/site/', $ts->root().'/public/site/', $newVal);
                $newVal = match ($key) {
                    'logo_path' => str_replace($ts->root().'/logo/', $ts->publicPath('site/logo').'/', $newVal),
                    'favicon_path' => str_replace($ts->root().'/favicon/', $ts->publicPath('site/favicon').'/', $newVal),
                    'hero_path' => str_replace($ts->root().'/hero/', $ts->publicPath('site/hero').'/', $newVal),
                    default => $newVal,
                };
                if ($newVal === $val) {
                    continue;
                }
                $this->line("  tenant {$tenantId} branding.{$key}");
                if (! $dry) {
                    $setting->update(['value' => $newVal]);
                    Cache::forget("tenant_settings.{$tenantId}.branding.{$key}");
                }
            }
        }
    }

    private function relocateSeoIntoPrivateSegment(bool $dry): void
    {
        $diskName = (string) config('seo.disk', 'local');
        $disk = Storage::disk($diskName);
        $this->info("SEO → tenants/{id}/private/site/… (диск [{$diskName}])…");

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $tenantId = (int) $tenantId;
            $ts = TenantStorage::forTrusted($tenantId);
            $r = $ts->root();
            $pairs = [
                [$r.'/seo', $ts->privatePath('site/seo')],
                [$r.'/site/seo', $ts->privatePath('site/seo')],
                [$r.'/seo-backups', $ts->privatePath('site/seo-backups')],
                [$r.'/site/seo-backups', $ts->privatePath('site/seo-backups')],
            ];
            foreach ($pairs as [$old, $new]) {
                if ($disk->exists($old) && $this->directoryHasFiles($disk, $old)) {
                    $this->line("  tenant {$tenantId}: {$old} → {$new}");
                    if (! $dry) {
                        if ($disk->exists($new)) {
                            $this->mergeDirectoryInto($disk, $old, $new);
                            $disk->deleteDirectory($old);
                        } else {
                            $disk->makeDirectory(dirname($new));
                            $disk->move($old, $new);
                        }
                    }
                }
            }
        }
    }

    private function rewriteSeoPathsInDatabase(bool $dry): void
    {
        $this->info('Обновление путей в tenant_seo_files / tenant_seo_file_generations…');

        $replacers = function (string $path, int $tenantId): string {
            $ts = TenantStorage::forTrusted($tenantId);
            $r = str_replace('\\', '/', $ts->root());
            $path = str_replace('\\', '/', $path);
            $path = str_replace($r.'/site/seo-backups/', $ts->privatePath('site/seo-backups').'/', $path);
            $path = str_replace($r.'/site/seo/', $ts->privatePath('site/seo').'/', $path);
            $path = str_replace($r.'/seo-backups/', $ts->privatePath('site/seo-backups').'/', $path);
            $path = str_replace($r.'/seo/', $ts->privatePath('site/seo').'/', $path);
            $path = str_replace($r.'/private/site/', $ts->privatePath('site').'/', $path);

            return $path;
        };

        if (! $dry) {
            TenantSeoFile::query()->orderBy('id')->chunkById(100, function ($rows) use ($replacers): void {
                foreach ($rows as $row) {
                    /** @var TenantSeoFile $row */
                    $tid = (int) $row->tenant_id;
                    $newPath = $replacers((string) $row->storage_path, $tid);
                    $newBackup = $row->backup_storage_path ? $replacers((string) $row->backup_storage_path, $tid) : null;
                    if ($newPath !== $row->storage_path || $newBackup !== $row->backup_storage_path) {
                        $row->update([
                            'storage_path' => $newPath,
                            'backup_storage_path' => $newBackup,
                        ]);
                    }
                }
            });

            TenantSeoFileGeneration::query()->whereNotNull('backup_storage_path')->orderBy('id')->chunkById(100, function ($rows) use ($replacers): void {
                foreach ($rows as $row) {
                    /** @var TenantSeoFileGeneration $row */
                    $tid = (int) $row->tenant_id;
                    $newBackup = $replacers((string) $row->backup_storage_path, $tid);
                    if ($newBackup !== $row->backup_storage_path) {
                        $row->update(['backup_storage_path' => $newBackup]);
                    }
                }
            });
        } else {
            $n = TenantSeoFile::query()->count();
            $this->line("  (dry-run) проверка до {$n} строк tenant_seo_files");
        }
    }

    private function linkBikesFolderToMotorcycles(bool $dry): void
    {
        // Legacy folder at disk root; must match MEDIA_DISK / tenant public disk where bikes/ was stored.
        $disk = Storage::disk(TenantStorageDisks::publicDiskName());
        if (! $disk->exists('bikes')) {
            $this->warn('Папка bikes/ не найдена — --link-bikes пропущен.');

            return;
        }

        $this->info('Привязка обложек из bikes/…');
        $linked = 0;
        foreach ($disk->files('bikes') as $relativePath) {
            $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                continue;
            }
            $stem = pathinfo($relativePath, PATHINFO_FILENAME);
            $normalized = str_replace('_', '-', $stem);

            $candidates = Motorcycle::withoutGlobalScopes()
                ->where(function ($q) use ($normalized, $stem): void {
                    $q->where('slug', 'like', '%'.$normalized.'%')
                        ->orWhere('slug', 'like', '%'.str_replace('-', '_', $stem).'%');
                })
                ->whereDoesntHave('media', fn ($q) => $q->where('collection_name', 'cover'))
                ->get();

            if ($candidates->count() !== 1) {
                if ($candidates->count() > 1) {
                    $this->warn("  Неоднозначно для [{$relativePath}], кандидатов: {$candidates->count()}");
                }

                continue;
            }

            $moto = $candidates->first();
            $this->line("  {$relativePath} → motorcycle #{$moto->id} ({$moto->slug})");
            $linked++;
            if (! $dry) {
                $moto->addMediaFromDisk(TenantStorageDisks::publicDiskName(), $relativePath)
                    ->usingFileName(basename($relativePath))
                    ->toMediaCollection('cover');
            }
        }

        $this->info($dry ? "  (dry-run) привязок: {$linked}" : "  привязано обложек: {$linked}");
    }

    private function tenantShouldReceiveMotoHeroTemplateAssets(Tenant $tenant): bool
    {
        $k = strtolower(trim((string) ($tenant->getAttributes()['theme_key'] ?? $tenant->theme_key ?? '')));

        return $k === '' || $k === 'moto' || $k === 'default';
    }

    /**
     * Кладёт дефолтные hero poster + видео в публичное хранилище тенанта (S3/R2 при настроенном диске),
     * чтобы {@see tenant_theme_public_url()} отдавал URL без legacy public/images.
     */
    private function seedMotoTemplateThemeAssets(): void
    {
        $prefix = config('themes.legacy_asset_url_prefix', config('tenant_landing.motolevins_public_prefix', 'images/motolevins'));
        $videoName = config('tenant_landing.motolevins_hero_video', 'Moto_levins_1.mp4');

        $videoSrc = public_path($prefix.'/videos/'.$videoName);
        if (! is_file($videoSrc)) {
            $videoSrc = resource_path('themes/moto/public/videos/'.$videoName);
        }

        $posterSrc = public_path($prefix.'/marketing/hero-bg.png');
        if (! is_file($posterSrc)) {
            $posterSrc = resource_path('themes/moto/public/marketing/hero-bg.png');
        }

        if (! is_file($videoSrc) && ! is_file($posterSrc)) {
            $this->warn('Нет исходных hero-ассетов в public и в resources/themes/moto/public — пропуск seed-theme-assets.');

            return;
        }

        $n = 0;
        foreach (Tenant::query()->cursor() as $tenant) {
            if (! $this->tenantShouldReceiveMotoHeroTemplateAssets($tenant)) {
                continue;
            }
            $ts = TenantStorage::forTrusted($tenant);
            if (is_file($videoSrc)) {
                $ts->putPublic('site/videos/'.$videoName, (string) file_get_contents($videoSrc));
                $this->line("  hero video → tenant #{$tenant->id} ({$tenant->slug})");
            }
            if (is_file($posterSrc)) {
                $ts->putPublic('site/marketing/hero-bg.png', (string) file_get_contents($posterSrc));
                $this->line("  hero poster → tenant #{$tenant->id} ({$tenant->slug})");
            }
            $n++;
        }

        $this->info("seed-theme-assets: обновлено тенантов: {$n}");
    }

    private function removeLegacySystemArchiveFolder(bool $dry): void
    {
        $disk = Storage::disk(TenantStorageDisks::publicDiskName());
        $legacy = 'tenants/_archive';
        if (! $disk->exists($legacy)) {
            return;
        }

        $this->info("Удаление устаревшего каталога [{$legacy}]…");
        if (! $dry) {
            $disk->deleteDirectory($legacy);
        }
    }
}
