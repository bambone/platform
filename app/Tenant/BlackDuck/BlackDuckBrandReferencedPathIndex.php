<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\Models\PageSection;
use App\Models\SeoMeta;
use App\Models\TenantMediaAsset;
use App\Models\TenantServiceProgram;
use App\Models\TenantSetting;
use App\Support\Storage\TenantStorage;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Support\Facades\Storage;

/**
 * Собирает множество логических путей {@code site/brand/...}, на которые ссылается рантайм/БД/секции,
 * чтобы безопасно находить кандидатов в «сиротских» файлах под {@code site/brand/}.
 */
final class BlackDuckBrandReferencedPathIndex
{
    /**
     * @return array<string, list<string>> path => [источник, …]
     */
    public static function collect(int $tenantId): array
    {
        $ref = [];
        $add = static function (string $path, string $source) use (&$ref): void {
            $p = self::normalizeToLogicalKey($path);
            if ($p === null) {
                return;
            }
            if (! str_starts_with($p, 'site/brand/')) {
                return;
            }
            $ref[$p] ??= [];
            $ref[$p][] = $source;
        };

        self::addCatalogAndDbAssets($tenantId, $add);
        $add(BlackDuckMediaCatalog::CATALOG_LOGICAL, 'media catalog file');

        self::addServicePrograms($tenantId, $add);
        self::addPageAndSeoText($tenantId, $add);
        self::addTenantSettings($tenantId, $add);
        self::addRuntimeResolverPaths($tenantId, $add);

        return $ref;
    }

    /**
     * @param  callable(string, string): void  $add
     */
    private static function addCatalogAndDbAssets(int $tenantId, callable $add): void
    {
        $cat = BlackDuckMediaCatalog::loadOrEmpty($tenantId);
        foreach ($cat['assets'] as $a) {
            if (! is_array($a)) {
                continue;
            }
            $main = trim((string) ($a['logical_path'] ?? ''));
            if ($main !== '') {
                $add($main, 'media catalog asset');
            }
            $poster = trim((string) ($a['poster_logical_path'] ?? ''));
            if ($poster !== '') {
                $add($poster, 'media catalog poster');
            }
            $deriv = is_array($a['derivatives'] ?? null) ? $a['derivatives'] : [];
            foreach ($deriv as $d) {
                if (! is_array($d)) {
                    continue;
                }
                $lp = trim((string) ($d['logical_path'] ?? ''));
                if ($lp !== '') {
                    $add($lp, 'media catalog derivative');
                }
            }
        }

        if (! class_exists(TenantMediaAsset::class) || ! \Illuminate\Support\Facades\Schema::hasTable('tenant_media_assets')) {
            return;
        }
        $rows = TenantMediaAsset::query()
            ->where('tenant_id', $tenantId)
            ->get(['logical_path', 'poster_logical_path', 'derivatives_json']);
        foreach ($rows as $row) {
            $add((string) $row->logical_path, 'tenant_media_assets.logical_path');
            $p = trim((string) $row->poster_logical_path);
            if ($p !== '') {
                $add($p, 'tenant_media_assets.poster');
            }
            $dj = is_array($row->derivatives_json) ? $row->derivatives_json : [];
            foreach ($dj as $d) {
                if (! is_array($d)) {
                    continue;
                }
                $lp = trim((string) ($d['logical_path'] ?? ''));
                if ($lp !== '') {
                    $add($lp, 'tenant_media_assets.derivatives_json');
                }
            }
        }
    }

    /**
     * @param  callable(string, string): void  $add
     */
    private static function addServicePrograms(int $tenantId, callable $add): void
    {
        $programs = TenantServiceProgram::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get(['cover_image_ref', 'cover_mobile_ref', 'catalog_meta_json']);
        foreach ($programs as $p) {
            foreach (['cover_image_ref' => (string) $p->cover_image_ref, 'cover_mobile_ref' => (string) $p->cover_mobile_ref] as $k => $v) {
                if (trim($v) === '') {
                    continue;
                }
                if (str_starts_with($v, 'http://') || str_starts_with($v, 'https://')) {
                    self::extractFromUrl($v, $add, 'tenant_service_programs.'.$k);
                } else {
                    $add($v, 'tenant_service_programs.'.$k);
                }
            }
            $meta = is_array($p->catalog_meta_json) ? $p->catalog_meta_json : [];
            self::walkMixedForPaths($meta, $add, 'tenant_service_programs.catalog_meta_json');
        }
    }

    /**
     * @param  callable(string, string): void  $add
     */
    private static function addPageAndSeoText(int $tenantId, callable $add): void
    {
        $sections = PageSection::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->cursor(['data_json', 'id']);

        foreach ($sections as $section) {
            $blob = is_array($section->data_json) ? json_encode($section->data_json, JSON_UNESCAPED_SLASHES) : '';
            if ($blob !== '') {
                foreach (self::extractBrandPathsFromText($blob) as $path) {
                    $add($path, 'page_sections.data_json#'.$section->id);
                }
            }
        }

        $seoRows = SeoMeta::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get(['id', 'og_image', 'json_ld']);

        foreach ($seoRows as $meta) {
            $og = trim((string) $meta->og_image);
            if ($og !== '') {
                if (str_starts_with($og, 'http://') || str_starts_with($og, 'https://')) {
                    self::extractFromUrl($og, $add, 'seo_meta.og_image#'.$meta->id);
                } else {
                    foreach (self::extractBrandPathsFromText($og) as $path) {
                        $add($path, 'seo_meta.og_image#'.$meta->id);
                    }
                }
            }
            $jsonLd = is_array($meta->json_ld) ? json_encode($meta->json_ld, JSON_UNESCAPED_SLASHES) : (string) $meta->json_ld;
            if ($jsonLd !== '' && $jsonLd !== '[]') {
                foreach (self::extractBrandPathsFromText($jsonLd) as $path) {
                    $add($path, 'seo_meta.json_ld#'.$meta->id);
                }
            }
        }
    }

    /**
     * @param  callable(string, string): void  $add
     */
    private static function addTenantSettings(int $tenantId, callable $add): void
    {
        $rows = TenantSetting::query()
            ->where('tenant_id', $tenantId)
            ->where(
                function ($q): void {
                    $q->where('value', 'like', '%site/brand%')
                        ->orWhere('value', 'like', '%/brand/%');
                }
            )
            ->get(['id', 'value', 'key', 'group']);

        foreach ($rows as $row) {
            $v = (string) $row->value;
            if ($v === '') {
                continue;
            }
            foreach (self::extractBrandPathsFromText($v) as $path) {
                $add($path, 'tenant_settings.'.$row->group.'.'.$row->key);
            }
        }
    }

    /**
     * Пути, которые рантайм Black Duck реально проверяет (hero, логотип, обложки услуг).
     *
     * @param  callable(string, string): void  $add
     */
    private static function addRuntimeResolverPaths(int $tenantId, callable $add): void
    {
        foreach (self::brandingPathCandidates() as $p) {
            $add($p, 'branding.candidates');
        }

        $stem = BlackDuckContentConstants::SERVICE_LANDING_HEADER_STEM;
        foreach (['png', 'jpg', 'jpeg', 'webp', 'avif', 'gif'] as $ext) {
            $add($stem.'.'.$ext, 'service_landing_hero.candidates');
        }

        $slugs = array_keys(BlackDuckServiceImages::sourceBasenameByMatrixSlug());
        foreach ($slugs as $slug) {
            if (str_starts_with((string) $slug, '#')) {
                continue;
            }
            $key = BlackDuckServiceImages::storageKeyForMatrixSlug($slug);
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                $add(BlackDuckServiceImages::PUBLIC_PREFIX.'/'.$key.'.'.$ext, 'service_image.candidates:'.$slug);
            }
        }

        $p = BlackDuckServiceImages::firstServiceLandingShadePath($tenantId);
        if ($p !== null) {
            $add($p, 'resolver:firstServiceLandingShadePath');
        }
        $h = BlackDuckServiceImages::firstHomeExpertHeroLogicalPath($tenantId);
        if ($h !== null) {
            $add($h, 'resolver:firstHomeExpertHeroLogicalPath');
        }
        $l = BlackDuckServiceImages::firstExistingServiceLandingHeaderPath($tenantId);
        if ($l !== null) {
            $add($l, 'resolver:firstExistingServiceLandingHeaderPath');
        }

        foreach ($slugs as $slug) {
            if (str_starts_with((string) $slug, '#')) {
                continue;
            }
            $c = BlackDuckServiceImages::firstServiceHubCardPublicPath($tenantId, (string) $slug);
            if ($c !== null) {
                $add($c, 'resolver:hubCard:'.(string) $slug);
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function brandingPathCandidates(): array
    {
        return [
            'site/brand/logo.jpg', 'site/brand/logo.jpeg', 'site/brand/logo.png', 'site/brand/logo.webp',
            'site/brand/hero-1916.jpg', 'site/brand/hero-1916.webp', 'site/brand/hero.png', 'site/brand/hero.jpg', 'site/brand/hero.jpeg', 'site/brand/hero.webp',
            'site/brand/hero-1600.webp', 'site/brand/hero-1280.webp',
        ];
    }

    /**
     * @param  array<string, mixed>  $mixed
     * @param  callable(string, string): void  $add
     */
    private static function walkMixedForPaths(array $mixed, callable $add, string $sourcePrefix): void
    {
        $stack = $mixed;
        $seen = 0;
        while ($stack !== [] && $seen < 2000) {
            $seen++;
            $v = array_pop($stack);
            if (is_string($v)) {
                if (str_contains($v, 'site/brand/') || str_contains($v, 'brand/')) {
                    foreach (self::extractBrandPathsFromText($v) as $p) {
                        $add($p, $sourcePrefix);
                    }
                }
            } elseif (is_array($v)) {
                foreach ($v as $child) {
                    if (is_array($child) || is_string($child)) {
                        $stack[] = $child;
                    }
                }
            }
        }
    }

    private static function extractFromUrl(string $url, callable $add, string $source): void
    {
        if (preg_match('#/tenants/\d+/public/(site/brand/[^?\s"\'#]+)#', $url, $m)) {
            $add($m[1], $source.'(url)');
        }
        foreach (self::extractBrandPathsFromText($url) as $p) {
            $add($p, $source);
        }
    }

    /**
     * @return list<string>
     */
    public static function extractBrandPathsFromText(string $text): array
    {
        $out = [];
        if ($text === '') {
            return $out;
        }
        if (preg_match_all('#\b(site/brand/[A-Za-z0-9][A-Za-z0-9._\-/]*)#', $text, $m)) {
            foreach ($m[1] as $p) {
                $p = (string) $p;
                $p = rtrim($p, '.,;)\'"\\');
                if ($p !== '' && str_starts_with($p, 'site/brand/')) {
                    $out[BlackDuckMediaCatalog::normalizeLogicalKey($p)] = true;
                }
            }
        }
        if (preg_match_all('#(?<![A-Za-z0-9/])brand/([A-Za-z0-9][A-Za-z0-9._\-/]*)#', $text, $m2)) {
            foreach ($m2[1] as $rel) {
                $rel = rtrim((string) $rel, '.,;)\'"\\');
                if ($rel === '') {
                    continue;
                }
                $p = BlackDuckMediaCatalog::normalizeLogicalKey('site/brand/'.$rel);
                if (str_starts_with($p, 'site/brand/')) {
                    $out[$p] = true;
                }
            }
        }

        return array_keys($out);
    }

    public static function normalizeToLogicalKey(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            if (preg_match('#/tenants/\d+/public/(site/brand/[^?\s"\'#]+)#', $raw, $m)) {
                $raw = $m[1];
            } else {
                return null;
            }
        } else {
            if (str_starts_with($raw, '/')) {
                if (preg_match('#/tenants/\d+/public/(site/brand/.+)$#', $raw, $m)) {
                    $raw = $m[1];
                }
            }
        }
        if (str_starts_with($raw, 'brand/') && ! str_starts_with($raw, 'site/')) {
            $raw = 'site/'.$raw;
        }
        if (! str_starts_with($raw, 'site/brand/')) {
            return null;
        }
        if (str_contains($raw, '..')) {
            return null;
        }

        return BlackDuckMediaCatalog::normalizeLogicalKey($raw);
    }

    /**
     * @return list<string> полные ключи на public-диске (tenants/{id}/public/...)
     */
    public static function allPublicFileObjectKeys(int $tenantId): array
    {
        $ts = TenantStorage::forTrusted($tenantId);
        $prefix = $ts->publicPath('site/brand');
        $disk = Storage::disk(TenantStorageDisks::publicDiskName());
        try {
            return $disk->allFiles($prefix);
        } catch (\Throwable) {
            return [];
        }
    }
}
