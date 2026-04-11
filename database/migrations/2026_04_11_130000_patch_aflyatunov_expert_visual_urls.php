<?php

use Database\Seeders\Tenant\AflyatunovExpertBootstrap;
use Illuminate\Database\Migrations\Migration;

/**
 * Подмешивает в page_sections URL бренд-фото (public/tenants/aflyatunov/*) для уже созданного тенанта.
 */
return new class extends Migration
{
    public function up(): void
    {
        AflyatunovExpertBootstrap::patchExpertVisualUrlsInDatabase();
    }

    public function down(): void
    {
        // Идемпотентный патч; откат вручную при необходимости.
    }
};
