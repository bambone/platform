<?php

use Database\Seeders\Tenant\AflyatunovExpertBootstrap;
use Illuminate\Database\Migrations\Migration;

/**
 * Меню expert-тенанта (отдельные страницы), брендинг в шапке, URL медиа через TenantStorage (R2/CDN).
 */
return new class extends Migration
{
    public function up(): void
    {
        AflyatunovExpertBootstrap::ensureExpertPublicBrandingForSlug();
        AflyatunovExpertBootstrap::ensureExpertMenuPagesForSlug();
        AflyatunovExpertBootstrap::patchExpertVisualUrlsInDatabase();
    }

    public function down(): void
    {
        // Идемпотентные правки; откат вручную при необходимости.
    }
};
