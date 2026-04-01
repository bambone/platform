<?php

namespace App\Support\Storage;

/**
 * Логические зоны внутри {@see TenantStorage} (после сегментов public/private).
 * Снижает опечатки в строковых путях и даёт автокомплит в IDE.
 */
enum TenantStorageArea: string
{
    /** {@code tenants/{id}/public/site/…} */
    case PublicSite = 'public_site';

    /** {@code tenants/{id}/private/site/seo/…} */
    case PrivateSeo = 'private_seo';

    /** {@code tenants/{id}/private/site/seo-backups/…} */
    case PrivateSeoBackups = 'private_seo_backups';

    /**
     * Базовый путь относительно сегмента public/ или private/ (без tenants/{id}/…).
     */
    public function relativeBase(): string
    {
        return match ($this) {
            self::PublicSite => 'site',
            self::PrivateSeo => 'site/seo',
            self::PrivateSeoBackups => 'site/seo-backups',
        };
    }

    public function isPublicDisk(): bool
    {
        return $this === self::PublicSite;
    }
}
