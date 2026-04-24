<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use App\Models\TenantMediaAsset;
use Illuminate\Support\Facades\Schema;

/**
 * Сигналы «тихой деградации» Black Duck: DB-first медиа и каталог услуг без согласованного сидирования/импорта.
 */
final class BlackDuckTenantRuntimeHealth
{
    /**
     * Тот же переключатель, что {@see BlackDuckMediaCatalog::isCatalogSourcedFromDatabaseForLoadPath()}
     * (ветка {@code loadOrEmpty} → БД, а не JSON).
     */
    public static function tenantMediaAssetsTableExists(): bool
    {
        return BlackDuckMediaCatalog::isCatalogSourcedFromDatabaseForLoadPath();
    }

    /**
     * Миграция на БД применена, а строк в {@code tenant_media_assets} нет — {@see BlackDuckMediaCatalog::loadOrEmpty}
     * не читает JSON, публичные proof/портфолио пусты до импорта.
     */
    public static function isMediaRuntimeEmptyInDatabase(int $tenantId): bool
    {
        if (! self::tenantMediaAssetsTableExists()) {
            return false;
        }

        return TenantMediaAsset::query()->where('tenant_id', $tenantId)->count() === 0;
    }

    public static function tenantServiceProgramsTableExists(): bool
    {
        try {
            return Schema::hasTable('tenant_service_programs');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Форма контактов (селектор услуг) ориентируется на {@see BlackDuckServiceProgramCatalog::inquiryFormServiceOptions}:
     * без видимых услуг селектор пуст, {@code inquiry_service_slug} не требуется.
     */
    public static function isServiceCatalogDegradedForInquiryForm(int $tenantId): bool
    {
        if (! self::tenantServiceProgramsTableExists()) {
            return true;
        }

        return ! BlackDuckServiceProgramCatalog::hasVisibleCatalogPrograms($tenantId);
    }
}
