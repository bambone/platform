<?php

use Database\Seeders\Tenant\AflyatunovExpertBootstrap;
use Illuminate\Database\Migrations\Migration;

/**
 * Expert tenant aflyatunov: второй визуальный проход — контент секций (короткие «запросы»),
 * видео в процессе и медиа-галерее, подзаголовок hero, снятие featured с «Парковка» в программах.
 */
return new class extends Migration
{
    public function up(): void
    {
        AflyatunovExpertBootstrap::patchAflyatunovExpertVisualPass2();
    }

    public function down(): void
    {
        // Идемпотентный патч; откат вручную при необходимости.
    }
};
