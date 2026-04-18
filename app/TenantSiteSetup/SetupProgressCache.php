<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use Illuminate\Support\Facades\Cache;

/**
 * Сводка прогресса зависит от прав текущего пользователя ({@see SetupApplicabilityEvaluator}),
 * поэтому ключ кеша включает tenant + user; при {@see forget()} поднимается ревизия тенанта,
 * чтобы сбросить закешированные варианты для всех пользователей без перечисления их id.
 */
final class SetupProgressCache
{
    public static function forget(int $tenantId): void
    {
        $vk = self::versionKey($tenantId);
        Cache::put($vk, (int) Cache::get($vk, 1) + 1, now()->addYears(10));
    }

    /**
     * @param  int|string|null  $userId  идентификатор пользователя (как у Auth::id()) или null без сессии
     */
    public static function key(int $tenantId, int|string|null $userId): string
    {
        $v = (int) Cache::get(self::versionKey($tenantId), 1);
        $u = ($userId === null || $userId === '') ? 'guest' : (string) $userId;

        return 'tenant_setup_summary.'.$tenantId.'.v'.$v.'.u'.$u;
    }

    private static function versionKey(int $tenantId): string
    {
        return 'tenant_setup_summary.rev.'.$tenantId;
    }
}
