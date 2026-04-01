<?php

namespace Tests\Unit;

use App\Support\Storage\TenantStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantBrandingAssetUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_storage_path_uses_public_disk_url(): void
    {
        $path = TenantStorage::for(1)->publicPath('site/logo/a.png');
        Storage::disk('public')->put($path, 'binary');

        $url = tenant_branding_asset_url($path, '');

        $this->assertNotSame('', $url);
        $this->assertStringContainsString($path, $url);
    }

    public function test_legacy_url_used_when_path_empty(): void
    {
        $this->assertSame(
            'https://cdn.example.com/logo.png',
            tenant_branding_asset_url('', 'https://cdn.example.com/logo.png')
        );
    }

    public function test_path_takes_precedence_over_legacy(): void
    {
        $path = TenantStorage::for(2)->publicPath('site/logo/b.png');
        Storage::disk('public')->put($path, 'x');

        $url = tenant_branding_asset_url($path, 'https://legacy.example/ignored.png');

        $this->assertStringContainsString($path, $url);
        $this->assertStringNotContainsString('legacy.example', $url);
    }

    public function test_empty_when_both_empty_or_null(): void
    {
        $this->assertSame('', tenant_branding_asset_url('', ''));
        $this->assertSame('', tenant_branding_asset_url(null, null));
    }
}
