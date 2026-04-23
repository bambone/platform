<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\Support\Storage\TenantStorage;

/**
 * JSON {@code site/brand/media-catalog.json} в хранилище тенанта: curated-медиа, без runtime Yandex.
 *
 * @phpstan-type AssetRow array{role: string, service_slug?: string, logical_path?: string, sort_order?: int, is_featured?: bool, kind?: string, poster_path?: string, before_group?: string, after_group?: string}
 */
final class BlackDuckMediaCatalog
{
    /** Логический путь к manifest в public-томе тенанта. */
    public const CATALOG_LOGICAL = 'site/brand/media-catalog.json';

    /**
     * Slug посадочных, для которых на странице услуги показывается mini-gallery (3–5 фото из catalog).
     *
     * @var list<string>
     */
    public const SERVICE_PROOF_LANDING_SLUGS = [
        'ppf',
        'keramika',
        'polirovka-kuzova',
        'himchistka-salona',
        'predprodazhnaya',
        'pdr',
    ];

    /**
     * @return array{version: int, assets: list<array<string, mixed>>}
     */
    public static function loadOrEmpty(int $tenantId): array
    {
        $ts = TenantStorage::forTrusted($tenantId);
        if (! $ts->existsPublic(self::CATALOG_LOGICAL)) {
            return ['version' => 1, 'assets' => []];
        }
        $raw = $ts->getPublic(self::CATALOG_LOGICAL);
        if (! is_string($raw) || $raw === '') {
            return ['version' => 1, 'assets' => []];
        }
        $d = json_decode($raw, true);
        if (! is_array($d)) {
            return ['version' => 1, 'assets' => []];
        }
        $assets = $d['assets'] ?? [];
        if (! is_array($assets)) {
            $assets = [];
        }

        return [
            'version' => (int) ($d['version'] ?? 1),
            'assets' => array_values(array_filter(
                $assets,
                static fn ($a): bool => is_array($a),
            )),
        ];
    }

    /**
     * @return list<array{ logical_path: string, caption?: string }>
     */
    public static function serviceGalleryImagePaths(int $tenantId, string $serviceSlug): array
    {
        $cat = self::loadOrEmpty($tenantId);
        $out = [];
        foreach ($cat['assets'] as $a) {
            if (($a['role'] ?? '') !== BlackDuckMediaRole::ServiceGallery->value) {
                continue;
            }
            if (($a['service_slug'] ?? '') !== $serviceSlug) {
                continue;
            }
            $p = trim((string) ($a['logical_path'] ?? ''));
            if ($p === '') {
                continue;
            }
            $out[] = [
                'sort' => (int) ($a['sort_order'] ?? 0),
                'logical_path' => $p,
                'caption' => trim((string) ($a['caption'] ?? '')),
            ];
        }
        usort($out, static fn (array $x, array $y): int => $x['sort'] <=> $y['sort']);
        $out = array_map(static fn (array $r): array => [
            'logical_path' => $r['logical_path'],
            'caption' => $r['caption'],
        ], $out);

        return array_slice($out, 0, 5);
    }

    /**
     * Non-empty string values only; omit a key if not present in the catalog.
     *
     * @return array{video?: string, poster?: string}
     */
    public static function featuredVideoForPage(int $tenantId, string $pageContext): array
    {
        $cat = self::loadOrEmpty($tenantId);
        $video = '';
        $poster = '';
        foreach ($cat['assets'] as $a) {
            if (! is_array($a)) {
                continue;
            }
            if (trim((string) ($a['page'] ?? $a['context'] ?? '')) !== $pageContext) {
                continue;
            }
            $role = (string) ($a['role'] ?? '');
            if ($role === BlackDuckMediaRole::FeaturedVideo->value) {
                $video = trim((string) ($a['logical_path'] ?? $a['video_path'] ?? ''));
                if ($poster === '' && (isset($a['poster_path']) || isset($a['poster_logical']))) {
                    $poster = trim((string) ($a['poster_path'] ?? $a['poster_logical'] ?? ''));
                }
            }
            if ($role === BlackDuckMediaRole::VideoPoster->value) {
                $poster = trim((string) ($a['logical_path'] ?? $a['poster_path'] ?? ''));
            }
        }

        $out = [];
        if ($video !== '') {
            $out['video'] = $video;
        }
        if ($poster !== '') {
            $out['poster'] = $poster;
        }

        return $out;
    }
}
