<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\Tenant\Expert\ExpertBrandMediaUrl;

/**
 * Публичные подписи и responsive-разметка для curated proof (alt/caption policy, srcset).
 */
final class BlackDuckProofDisplay
{
    /**
     * Alt: manifest alt → title → caption → service_label → название услуги по slug → безопасный fallback.
     */
    public static function altForItem(array $item, ?string $serviceSlug = null, ?int $tenantId = null): string
    {
        $alt = trim((string) ($item['alt'] ?? ''));
        if ($alt !== '') {
            return $alt;
        }
        foreach (['title', 'caption', 'task', 'summary', 'service_label'] as $k) {
            $t = trim((string) ($item[$k] ?? ''));
            if ($t !== '') {
                return $t;
            }
        }
        $slug = trim((string) ($item['service_slug'] ?? $serviceSlug ?? ''));
        if ($slug !== '' && ! str_starts_with($slug, '#')) {
            $title = $tenantId !== null && $tenantId > 0
                ? BlackDuckServiceProgramCatalog::serviceTitleForSlug($tenantId, $slug)
                : BlackDuckContentConstants::serviceTitleForSlug($slug);

            return 'Работа: '.$title;
        }

        return 'Фото работы Black Duck';
    }

    /**
     * CSS aspect-ratio value e.g. "4 / 3" or null.
     */
    public static function aspectRatioCss(?string $aspectHint): ?string
    {
        $h = trim((string) $aspectHint);
        if ($h === '') {
            return null;
        }
        if (preg_match('#^(\d+)\s*[/:]\s*(\d+)$#', $h, $m)) {
            return $m[1].' / '.$m[2];
        }

        return null;
    }

    /**
     * Строка srcset из derivatives или пусто (тогда один src в шаблоне).
     *
     * @param  list<array{w: int, logical_path: string}>  $derivatives
     */
    public static function srcsetFromDerivatives(int $tenantId, array $derivatives): string
    {
        $parts = [];
        foreach ($derivatives as $d) {
            if (! is_array($d)) {
                continue;
            }
            $w = (int) ($d['w'] ?? 0);
            $p = trim((string) ($d['logical_path'] ?? ''));
            if ($w < 1 || $p === '' || ! BlackDuckMediaCatalog::logicalPathIsUsable($tenantId, $p)) {
                continue;
            }
            $url = ExpertBrandMediaUrl::resolve($p);
            if ($url !== '') {
                $parts[] = $url.' '.$w.'w';
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Default sizes for gallery grid (browser picks best from srcset).
     */
    public static function defaultGallerySizes(): string
    {
        return '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw';
    }
}
