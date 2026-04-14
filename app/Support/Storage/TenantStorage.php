<?php

namespace App\Support\Storage;

use App\Models\Tenant;
use App\Tenant\CurrentTenant;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Логические пространства имён под дисками Laravel (см. {@see TenantStorageDisks}, {@code config('tenant_storage')}):
 *
 * - {@see publicPath}: публичный tenant-диск — {@code tenants/{id}/public/…}
 * - {@see privatePath}: приватный диск (SEO и т.п.) — {@code tenants/{id}/private/…}
 *
 * Общие системные файлы платформы (не привязаны к клиенту) — префикс {@see SYSTEM_POOL_PREFIX}
 * ({@code tenants/_system/…}). Не использовать для данных арендаторов.
 *
 * Предпочтительно писать через {@see putPublic}/{@see putPrivate} или {@see TenantStorageArea}.
 */
final class TenantStorage
{
    /** Префикс общих служебных путей на любом диске (не данные арендатора). */
    public const SYSTEM_POOL_PREFIX = 'tenants/_system';

    /** Каталог Spatie media относительно {@code tenants/{id}/public/}. */
    public const MEDIA_FOLDER = 'media';

    /**
     * Объектный ключ на public-диске для предустановленной темы (репозиторий + {@code php artisan theme:push-system-bundled}).
     * Пример: {@code tenants/_system/themes/moto/marketing/hero-bg.png}.
     */
    public static function systemBundledThemeObjectKey(string $themeKey, string $relativeWithinTheme): string
    {
        $k = strtolower(trim($themeKey, '/'));
        $rel = ltrim(str_replace('\\', '/', $relativeWithinTheme), '/');

        return self::SYSTEM_POOL_PREFIX.'/themes/'.$k.($rel !== '' ? '/'.$rel : '');
    }

    private function __construct(
        private readonly int $tenantId,
    ) {}

    public static function for(int|Tenant $tenant): self
    {
        $id = $tenant instanceof Tenant ? (int) $tenant->id : (int) $tenant;
        self::assertTenantMatchesRequestContext($id);

        return new self($id);
    }

    public static function forTrusted(int|Tenant $tenant): self
    {
        $id = $tenant instanceof Tenant ? (int) $tenant->id : (int) $tenant;

        return new self($id);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function forCurrent(): self
    {
        $t = \currentTenant();
        if ($t === null) {
            throw new InvalidArgumentException('No current tenant for TenantStorage::forCurrent().');
        }

        return new self((int) $t->id);
    }

    /**
     * Корень тенанта: {@code tenants/{id}} (без public/private).
     */
    public function root(): string
    {
        return 'tenants/'.$this->tenantId;
    }

    /**
     * Путь на публичном tenant-диске: {@code tenants/{id}/public/{path}}.
     */
    public function publicPath(string $path): string
    {
        return $this->root().'/public/'.ltrim($path, '/');
    }

    /**
     * Ключ на public-диске: {@code tenants/{id}/public/themes/{relative}}.
     */
    public function publicThemesPath(string $relativeWithinThemes = ''): string
    {
        $rel = ltrim(str_replace('\\', '/', $relativeWithinThemes), '/');

        return $this->publicPath($rel === '' ? 'themes' : 'themes/'.$rel);
    }

    /**
     * Полный ключ на public-диске для зоны {@see TenantStorageArea::PublicSite}.
     */
    public function publicPathInArea(TenantStorageArea $area, string $relativePath = ''): string
    {
        $base = $area->relativeBase();
        $logical = $relativePath === '' ? $base : $base.'/'.ltrim($relativePath, '/');

        if (! $area->isPublicDisk()) {
            throw new InvalidArgumentException('Area is not on the public disk.');
        }

        return $this->publicPath($logical);
    }

    /**
     * Полный ключ на приватном диске для зоны и файла.
     */
    public function privatePathInArea(TenantStorageArea $area, string $relativePath = ''): string
    {
        $base = $area->relativeBase();
        $logical = $relativePath === '' ? $base : $base.'/'.ltrim($relativePath, '/');

        if ($area->isPublicDisk()) {
            throw new InvalidArgumentException('Area is not on the private disk.');
        }

        return $this->privatePath($logical);
    }

    public function privatePath(string $path): string
    {
        return $this->root().'/private/'.ltrim($path, '/');
    }

    public function putInArea(TenantStorageArea $area, string $relativeFile, mixed $contents, array $options = []): bool
    {
        $base = $area->relativeBase();
        $logical = $base.'/'.ltrim($relativeFile, '/');

        return $area->isPublicDisk()
            ? $this->putPublic($logical, $contents, $options)
            : $this->putPrivate($logical, $contents, $options);
    }

    public function putPrivateAtomic(string $path, string $contents): bool
    {
        $disk = $this->privateDisk();
        $full = $this->privatePath($path);
        $dir = dirname($full);
        if (! $disk->exists($dir)) {
            $disk->makeDirectory($dir);
        }

        $oldSize = $disk->exists($full) ? (int) $disk->size($full) : 0;
        $newSize = strlen($contents);
        $delta = $newSize - $oldSize;
        $tenant = $this->quotaTenant();
        if ($tenant !== null && TenantStorageQuotaService::isQuotaEnforcementActive() && $delta > 0) {
            app(TenantStorageQuotaService::class)->assertCanStoreBytes($tenant, $delta, 'tenant_storage_private_atomic_put');
        }

        $tmp = $full.'.tmp.'.bin2hex(random_bytes(4));
        if (! $disk->put($tmp, $contents)) {
            return false;
        }

        if ($disk->exists($full)) {
            $disk->delete($full);
        }

        if (! $disk->move($tmp, $full)) {
            $disk->delete($tmp);

            return false;
        }

        if ($delta !== 0 && $tenant !== null) {
            app(TenantStorageQuotaService::class)->applyUsageDelta($tenant, $delta);
        }

        return true;
    }

    public function putPrivateAtomicInArea(TenantStorageArea $area, string $relativeFile, string $contents): bool
    {
        if ($area->isPublicDisk()) {
            throw new InvalidArgumentException('Atomic private write requires a private-disk area.');
        }
        $base = $area->relativeBase();
        $logical = $base.'/'.ltrim($relativeFile, '/');

        return $this->putPrivateAtomic($logical, $contents);
    }

    /**
     * Опции для PutObject на облачном public-диске: Cache-Control для edge/browser (см. tenant_storage.public_object_cache_control).
     * На локальном Flysystem возвращает {@code $options} без изменений.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function mergedOptionsForPublicObjectWrite(Filesystem $disk, array $options = []): array
    {
        if (TenantStorageDisks::usesLocalFlyAdapter($disk)) {
            return $options;
        }

        $cc = trim((string) config('tenant_storage.public_object_cache_control', ''));
        $defaults = ['visibility' => 'public'];
        if ($cc !== '') {
            $defaults['CacheControl'] = $cc;
        }

        return array_merge($defaults, $options);
    }

    public function putPublic(string $path, mixed $contents, array $options = []): bool
    {
        $fullKey = $this->publicPath($path);
        $disk = $this->publicDisk();
        $oldSize = $disk->exists($fullKey) ? (int) $disk->size($fullKey) : 0;
        $newSize = self::measureContentsByteLength($contents);
        $delta = $newSize - $oldSize;
        $tenant = $this->quotaTenant();
        if ($tenant !== null && TenantStorageQuotaService::isQuotaEnforcementActive() && $delta > 0) {
            app(TenantStorageQuotaService::class)->assertCanStoreBytes($tenant, $delta, 'tenant_storage_public_put');
        }

        $ok = app(TenantPublicMediaWriter::class)->putPublicObjectKey($this->tenantId, $fullKey, $contents, $options);
        if ($ok && $delta !== 0 && $tenant !== null) {
            app(TenantStorageQuotaService::class)->applyUsageDelta($tenant, $delta);
        }

        return $ok;
    }

    public function putPrivate(string $path, mixed $contents, array $options = []): bool
    {
        $fullKey = $this->privatePath($path);
        $disk = $this->privateDisk();
        $oldSize = $disk->exists($fullKey) ? (int) $disk->size($fullKey) : 0;
        $newSize = self::measureContentsByteLength($contents);
        $delta = $newSize - $oldSize;
        $tenant = $this->quotaTenant();
        if ($tenant !== null && TenantStorageQuotaService::isQuotaEnforcementActive() && $delta > 0) {
            app(TenantStorageQuotaService::class)->assertCanStoreBytes($tenant, $delta, 'tenant_storage_private_put');
        }

        $ok = $disk->put($fullKey, $contents, $options);
        if ($ok && $delta !== 0 && $tenant !== null) {
            app(TenantStorageQuotaService::class)->applyUsageDelta($tenant, $delta);
        }

        return $ok;
    }

    public function getPublic(string $path): ?string
    {
        $full = $this->publicPath($path);
        if (! $this->publicDisk()->exists($full)) {
            return null;
        }
        $raw = $this->publicDisk()->get($full);

        return is_string($raw) ? $raw : null;
    }

    public function getPrivate(string $path): ?string
    {
        $full = $this->privatePath($path);
        if (! $this->privateDisk()->exists($full)) {
            return null;
        }
        $raw = $this->privateDisk()->get($full);

        return is_string($raw) ? $raw : null;
    }

    public function existsPublic(string $path): bool
    {
        $fullKey = $this->publicPath($path);
        if ($this->publicDisk()->exists($fullKey)) {
            return true;
        }

        $mirrorName = TenantStorageDisks::publicMirrorDiskName();
        if ($mirrorName === $this->publicDiskName()) {
            return false;
        }

        return TenantStorageDisks::publicMirrorDisk()->exists($fullKey);
    }

    public function existsPrivate(string $path): bool
    {
        return $this->privateDisk()->exists($this->privatePath($path));
    }

    public function existsInArea(TenantStorageArea $area, string $relativePath = ''): bool
    {
        $key = $area->isPublicDisk()
            ? $this->publicPathInArea($area, $relativePath)
            : $this->privatePathInArea($area, $relativePath);

        return $area->isPublicDisk()
            ? $this->publicDisk()->exists($key)
            : $this->privateDisk()->exists($key);
    }

    /**
     * Удаляет все объекты с ключами под {@code tenants/{id}/public/site/{pathUnderSite}/…}.
     * Допустимо только для {@code expert_auto/programs} (обложки карточек программ).
     *
     * @return int число удалённых ключей
     */
    public function deleteAllPublicFilesUnderSitePath(string $pathUnderSite): int
    {
        $pathUnderSite = ltrim(str_replace('\\', '/', $pathUnderSite), '/');
        if ($pathUnderSite === '' || str_contains($pathUnderSite, '..')) {
            throw new InvalidArgumentException('Invalid site path segment.');
        }
        if ($pathUnderSite !== 'expert_auto/programs') {
            throw new InvalidArgumentException('Bulk delete is only allowed for expert_auto/programs.');
        }

        $fullPrefix = $this->publicPath('site/'.$pathUnderSite);
        $disk = $this->publicDisk();
        $writer = app(TenantPublicMediaWriter::class);
        $count = 0;
        try {
            $keys = $disk->allFiles($fullPrefix);
        } catch (Throwable) {
            return 0;
        }

        foreach ($keys as $key) {
            try {
                if ($writer->deletePublicObjectKey($this->tenantId, $key)) {
                    $count++;
                }
            } catch (Throwable) {
                // продолжаем
            }
        }

        return $count;
    }

    public function publicUrl(string $path): string
    {
        $relative = ltrim($this->publicPath($path), '/');
        $cdn = rtrim((string) config('tenant_storage.public_cdn_base_url', ''), '/');
        if ($cdn !== '') {
            $url = $cdn.'/'.$relative;
        } else {
            $adapter = $this->publicDisk();
            if (! $adapter instanceof FilesystemAdapter) {
                throw new RuntimeException('TenantStorage::publicUrl() requires a disk adapter that supports URL generation.');
            }

            $url = $adapter->url($this->publicPath($path));
        }

        $bust = trim((string) config('tenant_storage.public_url_version', ''));
        if ($bust !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?').$bust;
        }

        return $url;
    }

    /**
     * Pre-signed temporary URL for an object on the **private** tenant disk (S3-compatible: R2 private bucket).
     *
     * Path is the same logical segment as {@see getPrivate}/{@see putPrivate} (after {@code tenants/{id}/private/}),
     * e.g. {@code site/seo/robots.txt}.
     *
     * Do not use for public marketing assets; do not persist returned URLs in the database.
     *
     * @throws LogicException when the private disk is local — use an authorized download route instead
     */
    public function temporaryPrivateUrl(string $pathUnderPrivateTenantSegment, DateTimeInterface $expiration, array $options = []): string
    {
        $disk = Storage::disk(TenantStorageDisks::privateDiskName());
        if (TenantStorageDisks::usesLocalFlyAdapter($disk)) {
            throw new LogicException(
                'Private disk is local filesystem: use an authorized backend download (policy) instead of temporaryPrivateUrl().'
            );
        }
        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException('temporaryPrivateUrl() requires a FilesystemAdapter (e.g. s3/r2-private).');
        }

        return $disk->temporaryUrl($this->privatePath($pathUnderPrivateTenantSegment), $expiration, $options);
    }

    private function publicDisk(): Filesystem
    {
        return Storage::disk($this->publicDiskName());
    }

    private function privateDisk(): Filesystem
    {
        return Storage::disk($this->privateDiskName());
    }

    private function publicDiskName(): string
    {
        return TenantStorageDisks::publicDiskName();
    }

    private function privateDiskName(): string
    {
        return TenantStorageDisks::privateDiskName();
    }

    /**
     * Сборка пути {@code tenants/{segment}/…} для особых случаев (например {@code _unscoped} у Spatie).
     * Для обычных файлов клиента предпочитайте {@see for()} + {@see publicPath}/{@see privatePath}.
     * Не применяет guard контекста тенанта — только для генерации путей библиотекой медиа.
     */
    public static function rootedPath(string $tenantKey, string $path): string
    {
        return 'tenants/'.ltrim($tenantKey, '/').'/'.ltrim($path, '/');
    }

    private static function assertTenantMatchesRequestContext(int $tenantId): void
    {
        if (! config('tenant_storage.enforce_current_tenant_context', true)) {
            return;
        }

        if (! app()->bound(CurrentTenant::class)) {
            return;
        }

        $current = app(CurrentTenant::class);
        if ($current->isNonTenantHost || $current->tenant === null) {
            return;
        }

        if ((int) $current->tenant->id !== $tenantId) {
            throw new LogicException(
                'TenantStorage::for('.$tenantId.') is not allowed in tenant context for tenant #'.$current->tenant->id.'.'
            );
        }
    }

    private function quotaTenant(): ?Tenant
    {
        try {
            return Tenant::query()->find($this->tenantId);
        } catch (QueryException) {
            return null;
        }
    }

    private static function measureContentsByteLength(mixed $contents): int
    {
        if ($contents === null || $contents === '') {
            return 0;
        }
        if (is_string($contents)) {
            return strlen($contents);
        }
        if ($contents instanceof UploadedFile) {
            return (int) $contents->getSize();
        }
        if ($contents instanceof \SplFileInfo) {
            return (int) @filesize($contents->getPathname()) ?: 0;
        }
        if (is_resource($contents)) {
            $stat = @fstat($contents);

            return (int) ($stat['size'] ?? 0);
        }

        return 0;
    }
}
