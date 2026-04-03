<?php

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Support\Storage\TenantPublicAssetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantPublicAssetResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_returns_null(): void
    {
        $this->assertNull(TenantPublicAssetResolver::resolve(null, 1));
        $this->assertNull(TenantPublicAssetResolver::resolve('  ', 1));
    }

    public function test_http_url_passthrough(): void
    {
        $u = 'https://example.com/a.png';

        $this->assertSame($u, TenantPublicAssetResolver::resolve($u, 1));
    }

    public function test_object_key_mismatch_tenant_returns_null(): void
    {
        $key = 'tenants/99/public/site/x.png';

        $this->assertNull(TenantPublicAssetResolver::resolve($key, 1));
    }

    public function test_object_key_matching_tenant_builds_public_url(): void
    {
        config(['tenant_storage.public_cdn_base_url' => 'https://cdn.example']);

        $key = 'tenants/5/public/themes/default/hero.jpg';

        $out = TenantPublicAssetResolver::resolve($key, 5);

        $this->assertNotNull($out);
        $this->assertStringContainsString('tenants/5/public/themes/default/hero.jpg', $out);
    }

    public function test_bare_relative_path_uses_public_url(): void
    {
        config(['tenant_storage.public_cdn_base_url' => 'https://cdn.example']);

        $out = TenantPublicAssetResolver::resolve('site/logo/a.png', 5);

        $this->assertStringContainsString('tenants/5/public/site/logo/a.png', $out);
    }

    public function test_resolve_hero_video_https_passthrough(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Hero',
            'slug' => 'hero-'.Str::random(8),
            'theme_key' => 'moto',
            'status' => 'trial',
        ]);
        $u = 'https://cdn.example.com/a.mp4';

        $this->assertSame($u, TenantPublicAssetResolver::resolveHeroVideo($u, $tenant));
    }

    public function test_resolve_hero_video_site_path_when_object_exists(): void
    {
        Storage::fake('hero-r2');
        config(['tenant_storage.public_disk' => 'hero-r2']);
        $tenant = Tenant::query()->create([
            'name' => 'Hero',
            'slug' => 'hero-'.Str::random(8),
            'theme_key' => 'moto',
            'status' => 'trial',
        ]);
        Storage::disk('hero-r2')->put('tenants/'.$tenant->id.'/public/site/videos/h.mp4', '12345');

        $out = TenantPublicAssetResolver::resolveHeroVideo('site/videos/h.mp4', $tenant);

        $this->assertNotNull($out);
        $this->assertStringContainsString('h.mp4', $out);
    }

    public function test_resolve_hero_video_returns_null_when_file_missing(): void
    {
        Storage::fake('hero-r2');
        config(['tenant_storage.public_disk' => 'hero-r2']);
        $tenant = Tenant::query()->create([
            'name' => 'Hero',
            'slug' => 'hero-'.Str::random(8),
            'theme_key' => 'moto',
            'status' => 'trial',
        ]);

        $this->assertNull(TenantPublicAssetResolver::resolveHeroVideo('site/videos/nope.mp4', $tenant));
    }

    public function test_resolve_hero_video_legacy_images_path_when_site_has_file(): void
    {
        Storage::fake('hero-r2');
        config(['tenant_storage.public_disk' => 'hero-r2']);
        $tenant = Tenant::query()->create([
            'name' => 'Hero',
            'slug' => 'hero-'.Str::random(8),
            'theme_key' => 'moto',
            'status' => 'trial',
        ]);
        Storage::disk('hero-r2')->put('tenants/'.$tenant->id.'/public/site/videos/Moto_levins_1.mp4', 'x');

        $out = TenantPublicAssetResolver::resolveHeroVideo('images/motolevins/videos/Moto_levins_1.mp4', $tenant);

        $this->assertNotNull($out);
    }
}
