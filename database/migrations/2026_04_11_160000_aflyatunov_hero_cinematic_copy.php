<?php

use Database\Seeders\Tenant\AflyatunovExpertBootstrap;
use Illuminate\Database\Migrations\Migration;

/**
 * Expert tenant aflyatunov: короткий кинематографичный hero (H1 + один подзаголовок, 4 бейджа).
 * Не затирает hero_video_url — вводное видео остаётся в data_json.
 */
return new class extends Migration
{
    public function up(): void
    {
        AflyatunovExpertBootstrap::patchAflyatunovHeroCinematicCopy();
    }

    public function down(): void
    {
        // Идемпотентный патч; откат вручную при необходимости.
    }
};
