<?php

use Database\Seeders\Tenant\AflyatunovExpertBootstrap;
use Illuminate\Database\Migrations\Migration;

/**
 * Expert tenant aflyatunov: визуальный и контентный слой лендинга (ТЗ 2026-04), программы с audience/outcomes, демо-отзывы.
 */
return new class extends Migration
{
    public function up(): void
    {
        AflyatunovExpertBootstrap::patchAflyatunovExpertDesignTz2026();
    }

    public function down(): void
    {
        // Идемпотентный патч; откат вручную при необходимости.
    }
};
