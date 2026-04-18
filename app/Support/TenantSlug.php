<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;

/**
 * Единый контракт URL-идентификатора клиента (поддомен / slug).
 *
 * Проверка уникальности через перебор и {@see normalize()} устойчива к legacy-строкам в БД, но O(n) и не даёт
 * инварианта на уровне СУБД. Долгосрочно: отдельная колонка нормализованного slug, backfill и уникальный индекс на неё.
 */
final class TenantSlug
{
    /**
     * Trim, lower case, только [a-z0-9-], без двойных дефисов и без дефисов по краям.
     */
    public static function normalize(string $input): string
    {
        $s = trim(mb_strtolower($input));
        $s = str_replace('_', '-', $s);
        $s = preg_replace('/[^a-z0-9-]+/', '-', $s) ?? '';
        $s = preg_replace('/-+/', '-', $s) ?? '';

        return trim($s, '-');
    }

    public static function isValidProductSlug(string $normalized): bool
    {
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $normalized);
    }

    /**
     * Занят ли URL-идентификатор после {@see normalize()} для **любой** строки `tenants.slug` в БД.
     *
     * Сравнение идёт через {@see normalize()} по каждой записи: старые значения с другим регистром,
     * пробелами или лишними символами не обходят проверку, если после нормализации совпадают с $normalized.
     */
    public static function isNormalizedSlugTaken(string $normalized, ?int $ignoreTenantId = null): bool
    {
        if ($normalized === '') {
            return false;
        }

        $query = Tenant::query();
        if ($ignoreTenantId !== null) {
            $query->whereKeyNot($ignoreTenantId);
        }

        foreach ($query->cursor() as $tenant) {
            if (self::normalize((string) $tenant->slug) === $normalized) {
                return true;
            }
        }

        return false;
    }
}
