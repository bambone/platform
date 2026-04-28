<?php

declare(strict_types=1);

namespace App\Support\Storage;

/**
 * Приводит значения {@code image_url} / логических путей из БД и импортов к канону {@code site/...} для проверок и резолверов.
 *
 * Поддерживает: {@code site/...}, {@code /site/...}, фрагменты вида {@code tenants/{id}/public/site/...}, относительные ключи под {@code site/brand/}.
 */
final class TenantPublicStoredPathNormalizer
{
    /**
     * @return non-empty-string|null канонический путь {@code site/...} или {@code null}, если внешний URL / пусто / не распознано
     */
    public static function toLogicalSitePath(string $stored, ?int $tenantId = null): ?string
    {
        $s = trim(str_replace('\\', '/', $stored));
        if ($s === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $s) === 1) {
            return null;
        }

        if (preg_match('#/public/(site/.+)$#i', $s, $m)) {
            return $m[1];
        }

        if (str_starts_with($s, '/site/')) {
            return ltrim($s, '/');
        }

        if (str_starts_with($s, 'site/')) {
            return $s;
        }

        if ($tenantId !== null && preg_match('#tenants/'.preg_quote((string) $tenantId, '#').'/public/(site/.+)$#i', $s, $m)) {
            return $m[1];
        }

        return 'site/brand/'.ltrim($s, '/');
    }
}
