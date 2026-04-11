<?php

use Database\Seeders\Tenant\AflyatunovExpertBootstrap;
use Illuminate\Database\Migrations\Migration;

/**
 * Тематические обложки карточек программ: бандл WebP → публичный диск тенанта (в т.ч. R2) + cover_image_ref.
 */
return new class extends Migration
{
    public function up(): void
    {
        AflyatunovExpertBootstrap::syncProgramCoverAssetsToTenantPublicDisk();
    }

    public function down(): void
    {
        // Не удаляем объекты в bucket: могут использоваться в проде; сброс обложек — вручную в админке.
    }
};
