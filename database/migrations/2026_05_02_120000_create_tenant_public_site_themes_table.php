<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_public_site_themes', function (Blueprint $table) {
            $table->id();
            $table->string('theme_key', 64)->unique()->comment('Совпадает с tenants.theme_key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        $now = now();

        foreach ([
            ['theme_key' => 'default', 'name' => 'По умолчанию', 'description' => 'Нейтральный публичный сайт.', 'sort_order' => 10],
            ['theme_key' => 'moto', 'name' => 'Мото', 'description' => 'Аренда мото и каталог (ядро платформы).', 'sort_order' => 20],
            ['theme_key' => 'expert_auto', 'name' => 'Инструктор / автошкола (expert_auto)', 'description' => 'Эксперт и запись на обучение.', 'sort_order' => 30],
            ['theme_key' => 'expert_pr', 'name' => 'PR и коммуникации (expert_pr)', 'description' => 'B2B PR, коммуникации, англоязычные сценарии лидогенерации.', 'sort_order' => 35],
            ['theme_key' => 'advocate_editorial', 'name' => 'Адвокат / персональный бренд (advocate_editorial)', 'description' => 'Персональный бренд, услуги, редакционный стиль.', 'sort_order' => 40],
            ['theme_key' => 'black_duck', 'name' => 'Детейлинг / Black Duck (black_duck)', 'description' => 'Детейлинг и визуально насыщенные кейсы.', 'sort_order' => 50],
        ] as $row) {
            DB::table('tenant_public_site_themes')->insert([
                'theme_key' => $row['theme_key'],
                'name' => $row['name'],
                'description' => $row['description'],
                'is_active' => true,
                'sort_order' => $row['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_public_site_themes');
    }
};
