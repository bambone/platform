<?php

namespace Tests\Unit\Themes;

use App\Support\Storage\TenantStorage;
use App\Themes\ThemeRegistry;
use Tests\TestCase;

class ThemeRegistryTest extends TestCase
{
    public function test_loads_moto_manifest_from_resources(): void
    {
        $r = app(ThemeRegistry::class);
        $def = $r->get('moto');

        $this->assertSame('moto', $def->key);
        $this->assertNotEmpty($def->assetWebPrefix);
        $this->assertContains('hero', $def->sections);
    }

    public function test_asset_url_uses_legacy_when_primary_file_missing(): void
    {
        $r = app(ThemeRegistry::class);
        $url = $r->assetUrl('moto', 'marketing/nonexistent-xyz-12345.png');

        $this->assertStringContainsString(config('themes.legacy_asset_url_prefix', 'images/motolevins'), $url);
    }

    public function test_asset_url_uses_theme_build_route_when_bundled_file_exists_in_resources(): void
    {
        if (is_file(public_path('themes/moto/marketing/hero-bg.png'))) {
            $this->markTestSkipped('public/themes shadows resources; prune public/themes or run without sync.');
        }

        $r = app(ThemeRegistry::class);
        $url = $r->assetUrl('moto', 'marketing/hero-bg.png');

        $this->assertStringContainsString('theme/build/moto', $url);
    }

    public function test_expert_auto_icons_use_moto_bundled_before_legacy_motolevins(): void
    {
        $r = app(ThemeRegistry::class);
        $url = $r->assetUrl('expert_auto', 'icons/icon-192.png');

        $this->assertStringContainsString('theme/build/moto', $url);
        $this->assertStringNotContainsString('motolevins', $url);
    }

    public function test_invalid_theme_key_falls_back_to_default(): void
    {
        $r = app(ThemeRegistry::class);
        $def = $r->get('../../../x');

        $this->assertSame((string) config('themes.default_key', 'moto'), $def->key);
    }

    public function test_system_bundled_theme_object_key(): void
    {
        $this->assertSame(
            'tenants/_system/themes/moto/marketing/hero-bg.png',
            TenantStorage::systemBundledThemeObjectKey('moto', 'marketing/hero-bg.png')
        );
    }
}
