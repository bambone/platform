<?php

use Database\Seeders\Tenant\AflyatunovExpertBootstrap;
use Illuminate\Database\Migrations\Migration;

/**
 * Для БД, где миграция 2026_04_11_090000 уже выполнилась со старым телом «tenant exists → return»:
 * добиваем демо-страницу home, секции конструктора, домен aflyatunov.rentbase.local и спутники.
 */
return new class extends Migration
{
    public function up(): void
    {
        AflyatunovExpertBootstrap::ensureDemoContentForSlug();
    }

    public function down(): void
    {
        // Идемпотентное наполнение; откат вручную при необходимости.
    }
};
