<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2: рабочий og:image для главной (ассет темы вместо legacy public/images) и актуализация текстов llms/route для /motorcycles.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenantId = (int) DB::table('tenants')->where('slug', 'motolevins')->value('id');
        if ($tenantId < 1) {
            return;
        }

        $themeKey = trim((string) DB::table('tenants')->where('id', $tenantId)->value('theme_key'));
        if ($themeKey === '') {
            $themeKey = 'moto';
        }

        $domain = rtrim((string) DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('group', 'general')
            ->where('key', 'domain')
            ->value('value'), '/');

        if ($domain !== '') {
            $ogImage = $domain.'/theme/build/'.$themeKey.'/marketing/hero-bg.png';
            $homePageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');
            if ($homePageId > 0) {
                DB::table('seo_meta')
                    ->where('tenant_id', $tenantId)
                    ->where('seoable_type', 'App\\Models\\Page')
                    ->where('seoable_id', $homePageId)
                    ->update([
                        'og_image' => $ogImage,
                        'updated_at' => now(),
                    ]);
            }
        }

        $llmsRow = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('group', 'seo')
            ->where('key', 'llms_entries')
            ->first();
        if ($llmsRow !== null && is_string($llmsRow->value) && $llmsRow->value !== '') {
            $decoded = json_decode($llmsRow->value, true);
            if (is_array($decoded)) {
                foreach ($decoded as $i => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    if (($item['path'] ?? '') === '/motorcycles') {
                        $decoded[$i]['summary'] = 'Полный каталог моделей с карточками, ценами за сутки и ссылками на бронирование и условия.';
                        break;
                    }
                }
                DB::table('tenant_settings')
                    ->where('tenant_id', $tenantId)
                    ->where('group', 'seo')
                    ->where('key', 'llms_entries')
                    ->update([
                        'value' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                Cache::forget("tenant_settings.{$tenantId}.seo.llms_entries");
            }
        }

        $routeRow = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('group', 'seo')
            ->where('key', 'route_overrides')
            ->first();
        if ($routeRow !== null && is_string($routeRow->value) && $routeRow->value !== '') {
            $routes = json_decode($routeRow->value, true);
            if (is_array($routes) && isset($routes['motorcycles.index']) && is_array($routes['motorcycles.index'])) {
                $routes['motorcycles.index']['description'] = 'Каталог Honda в прокате на море: карточки с ценами за сутки, сценарии поездок и ссылки на бронирование и условия аренды.';
                DB::table('tenant_settings')
                    ->where('tenant_id', $tenantId)
                    ->where('group', 'seo')
                    ->where('key', 'route_overrides')
                    ->update([
                        'value' => json_encode($routes, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]);
                Cache::forget("tenant_settings.{$tenantId}.seo.route_overrides");
            }
        }
    }

    public function down(): void
    {
        $tenantId = (int) DB::table('tenants')->where('slug', 'motolevins')->value('id');
        if ($tenantId < 1) {
            return;
        }

        $domain = rtrim((string) DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('group', 'general')
            ->where('key', 'domain')
            ->value('value'), '/');
        if ($domain !== '') {
            $legacyOg = $domain.'/images/motolevins/marketing/hero-bg.png';
            $homePageId = (int) DB::table('pages')->where('tenant_id', $tenantId)->where('slug', 'home')->value('id');
            if ($homePageId > 0) {
                DB::table('seo_meta')
                    ->where('tenant_id', $tenantId)
                    ->where('seoable_type', 'App\\Models\\Page')
                    ->where('seoable_id', $homePageId)
                    ->update([
                        'og_image' => $legacyOg,
                        'updated_at' => now(),
                    ]);
            }
        }

        Cache::forget("tenant_settings.{$tenantId}.seo.llms_entries");
        Cache::forget("tenant_settings.{$tenantId}.seo.route_overrides");
    }
};
