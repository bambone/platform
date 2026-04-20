<?php

namespace App\Tenant\Expert;

use App\Http\Controllers\HomeController;
use App\Models\Tenant;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageArea;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Обложки программ expert_auto: по умолчанию из фото {@code site/brand/*} тенанта (реальные кадры),
 * иначе копия из {@code tenants/_system/themes/expert_auto/program-covers/}.
 */
final class ExpertAutoProgramCoverInstaller
{
    private const DESKTOP_W = 1200;

    private const DESKTOP_H = 640;

    private const MOBILE_W = 720;

    private const MOBILE_H = 1040;

    /** Логические пути под {@code site/} на публичном диске тенанта. */
    private const BRAND_RELATIVE_CANDIDATES = [
        'brand/hero.jpg',
        'brand/hero.webp',
        'brand/portrait.jpg',
        'brand/portrait.webp',
        'brand/process-accent.jpg',
        'brand/process-accent.webp',
        'brand/credentials-bg.jpg',
        'brand/credentials-bg.webp',
    ];

    public function installFromSystemBundledPool(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null || $tenant->theme_key !== ExpertAutoProgramCoverRegistry::THEME_KEY) {
            Log::debug('Expert program covers: skip tenant (missing or not expert_auto theme).', ['tenant_id' => $tenantId]);

            return;
        }

        if (! Schema::hasTable('tenant_service_programs') || ! Schema::hasColumn('tenant_service_programs', 'cover_image_ref')) {
            return;
        }

        $disk = Storage::disk(TenantStorageDisks::publicDiskName());
        $ts = TenantStorage::forTrusted($tenantId);
        $now = now();
        $hasMobile = Schema::hasColumn('tenant_service_programs', 'cover_mobile_ref');
        $hasAlt = Schema::hasColumn('tenant_service_programs', 'cover_image_alt');
        $altBySlug = ExpertAutoProgramCoverRegistry::imageAltByProgramSlug();

        $preferBrand = (bool) config('expert_auto.program_covers_prefer_brand_photography', true);
        $brandObjectKeys = $preferBrand ? $this->existingBrandObjectKeys($disk, $tenantId) : [];
        $brandBytesCache = [];

        foreach (ExpertAutoProgramCoverRegistry::relativeFilesByProgramSlug() as $slug => $files) {
            $bytesDesktop = null;
            $bytesMobile = null;

            if ($brandObjectKeys !== []) {
                $objectKey = $brandObjectKeys[$this->stableIndex($slug, count($brandObjectKeys))];
                if (! isset($brandBytesCache[$objectKey])) {
                    $raw = $disk->get($objectKey);
                    $brandBytesCache[$objectKey] = is_string($raw) && $raw !== '' ? $raw : null;
                }
                $brandRaw = $brandBytesCache[$objectKey];
                if (is_string($brandRaw) && $brandRaw !== '') {
                    $pair = $this->webpPairFromRasterBytes($brandRaw, $slug);
                    if (is_array($pair)) {
                        [$bytesDesktop, $bytesMobile] = $pair;
                    }
                }
            }

            if (! is_string($bytesDesktop) || $bytesDesktop === '') {
                [$bytesDesktop, $bytesMobile] = $this->loadFromSystemPool($disk, $files);
            }

            if (! is_string($bytesDesktop) || $bytesDesktop === '') {
                // Ожидаемо, пока нет бренд-фото или системного пула; не засоряем warning-логи тестами и девом.
                Log::debug('Expert program covers: нет данных для slug', [
                    'slug' => $slug,
                    'tenant_id' => $tenantId,
                    'hint' => 'Залейте site/brand/hero.jpg (и др.) или php artisan expert:seed-system-program-covers',
                ]);

                continue;
            }

            if (! is_string($bytesMobile) || $bytesMobile === '') {
                $bytesMobile = $bytesDesktop;
            }

            $this->putCoversAndUpdateRow(
                $ts,
                $tenantId,
                $slug,
                $bytesDesktop,
                $bytesMobile,
                $now,
                $hasMobile,
                $hasAlt,
                $altBySlug[$slug] ?? null,
            );
        }

        HomeController::forgetCachedPayloadForTenant($tenantId);
    }

    /**
     * @return list<string> полные ключи на публичном диске
     */
    private function existingBrandObjectKeys($disk, int $tenantId): array
    {
        $ts = TenantStorage::forTrusted($tenantId);
        $out = [];
        foreach (self::BRAND_RELATIVE_CANDIDATES as $rel) {
            $key = $ts->publicPath('site/'.$rel);
            if ($disk->exists($key)) {
                $out[] = $key;
            }
        }

        return $out;
    }

    private function stableIndex(string $slug, int $mod): int
    {
        if ($mod <= 0) {
            return 0;
        }

        return (int) (crc32($slug) % $mod + $mod) % $mod;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function webpPairFromRasterBytes(string $bytes, string $slug): ?array
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }
        /** @var \GdImage|resource|false $src */
        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }
        try {
            /* Меньше anchorY → кроп ближе к верху исходника (лица/головы в кадре). */
            $anchorX = 0.22 + ($this->stableIndex($slug.'#cx', 40) / 100.0);
            $anchorY = 0.05 + ($this->stableIndex($slug.'#cy', 28) / 100.0);
            $desktop = $this->coverResizeToWebp($src, self::DESKTOP_W, self::DESKTOP_H, $anchorX, $anchorY);
            $mobile = $this->coverResizeToWebp($src, self::MOBILE_W, self::MOBILE_H, $anchorX, $anchorY);
            if ($desktop === null || $mobile === null) {
                return null;
            }

            return [$desktop, $mobile];
        } finally {
            imagedestroy($src);
        }
    }

    /**
     * Центровка кропа с лёгким смещением якоря по slug (разные кадры из одного hero).
     *
     * @param  \GdImage|resource  $src
     */
    private function coverResizeToWebp($src, int $tw, int $th, float $anchorX, float $anchorY): ?string
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw < 1 || $sh < 1) {
            return null;
        }

        $scale = max($tw / $sw, $th / $sh);
        $nw = (int) max(1, round($sw * $scale));
        $nh = (int) max(1, round($sh * $scale));

        $scaled = imagescale($src, $nw, $nh);
        if ($scaled === false) {
            return null;
        }

        $maxX = max(0, $nw - $tw);
        $maxY = max(0, $nh - $th);
        $x = (int) round($maxX * min(1.0, max(0.0, $anchorX)));
        $y = (int) round($maxY * min(1.0, max(0.0, $anchorY)));

        $out = imagecreatetruecolor($tw, $th);
        if ($out === false) {
            imagedestroy($scaled);

            return null;
        }
        imagecopy($out, $scaled, 0, 0, $x, $y, $tw, $th);

        ob_start();
        $ok = imagewebp($out, null, 88);
        $buf = ob_get_clean();

        imagedestroy($out);
        imagedestroy($scaled);

        return $ok && is_string($buf) && $buf !== '' ? $buf : null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function loadFromSystemPool($disk, array $files): array
    {
        $fromDesktop = TenantStorage::systemBundledThemeObjectKey(
            ExpertAutoProgramCoverRegistry::THEME_KEY,
            'program-covers/'.$files['desktop'],
        );
        if (! $disk->exists($fromDesktop)) {
            return [null, null];
        }
        $bytesDesktop = $disk->get($fromDesktop);
        if (! is_string($bytesDesktop) || $bytesDesktop === '') {
            return [null, null];
        }
        $fromMobile = TenantStorage::systemBundledThemeObjectKey(
            ExpertAutoProgramCoverRegistry::THEME_KEY,
            'program-covers/'.$files['mobile'],
        );
        $bytesMobile = ($disk->exists($fromMobile) ? $disk->get($fromMobile) : null);
        if (! is_string($bytesMobile) || $bytesMobile === '') {
            $bytesMobile = $bytesDesktop;
        }

        return [$bytesDesktop, $bytesMobile];
    }

    private function putCoversAndUpdateRow(
        TenantStorage $ts,
        int $tenantId,
        string $slug,
        string $bytesDesktop,
        string $bytesMobile,
        $now,
        bool $hasMobile,
        bool $hasAlt,
        ?string $alt,
    ): void {
        $relDesktop = 'expert_auto/programs/'.$slug.'/card-cover-desktop.webp';
        $relMobile = 'expert_auto/programs/'.$slug.'/card-cover-mobile.webp';

        $ts->putInArea(TenantStorageArea::PublicSite, $relDesktop, $bytesDesktop, [
            'visibility' => 'public',
            'ContentType' => 'image/webp',
        ]);
        $ts->putInArea(TenantStorageArea::PublicSite, $relMobile, $bytesMobile, [
            'visibility' => 'public',
            'ContentType' => 'image/webp',
        ]);

        $keyDesktop = 'tenants/'.$tenantId.'/public/site/'.$relDesktop;
        $keyMobile = 'tenants/'.$tenantId.'/public/site/'.$relMobile;

        $payload = [
            'cover_image_ref' => $keyDesktop,
            'updated_at' => $now,
        ];
        if ($hasMobile) {
            $payload['cover_mobile_ref'] = $keyMobile;
        }
        if ($hasAlt) {
            $payload['cover_image_alt'] = $alt;
        }

        DB::table('tenant_service_programs')
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->update($payload);
    }
}
