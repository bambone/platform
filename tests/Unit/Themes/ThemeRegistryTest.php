<?php

namespace Tests\Unit\Themes;

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

    public function test_invalid_theme_key_falls_back_to_default(): void
    {
        $r = app(ThemeRegistry::class);
        $def = $r->get('../../../x');

        $this->assertSame((string) config('themes.default_key', 'moto'), $def->key);
    }
}
