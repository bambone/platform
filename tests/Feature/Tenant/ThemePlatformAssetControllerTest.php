<?php

namespace Tests\Feature\Tenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class ThemePlatformAssetControllerTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_serves_png_from_resources_themes_public(): void
    {
        $tenant = $this->createTenantWithActiveDomain('themeasset');
        $dir = resource_path('themes/moto/public/marketing');
        File::ensureDirectoryExists($dir);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        File::put($dir.'/fixture-theme-asset.png', $png);

        $host = $this->tenancyHostForSlug('themeasset');
        $response = $this->get('http://'.$host.'/theme/build/moto/marketing/fixture-theme-asset.png');

        $response->assertOk();
        File::delete($dir.'/fixture-theme-asset.png');
    }
}
