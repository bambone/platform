<?php

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\Storage\TenantPublicAssetResolver;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class TenantPublicAssetResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

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

    public function test_https_cdn_tenants_public_path_rewrites_for_local_delivery(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Local delivery',
            'slug' => 'ld-'.Str::random(8),
            'theme_key' => 'expert_auto',
            'status' => 'trial',
            'media_delivery_mode_override' => 'local',
        ]);
        $tid = (int) $tenant->id;

        config([
            'tenant_storage.public_cdn_base_url' => '',
            'tenant_storage.media_local_public_base_path' => '/media',
            'tenant_storage.public_url_version' => '',
        ]);

        TenantSetting::setForTenant($tid, 'general.domain', 'https://aflyatunov.example');

        $cdnUrl = 'https://media.rentbase.su/tenants/'.$tid.'/public/site/brand/hero.jpg?x=1';
        $out = TenantPublicAssetResolver::resolve($cdnUrl, $tid);

        $this->assertSame(
            'https://aflyatunov.example/media/tenants/'.$tid.'/public/site/brand/hero.jpg?x=1',
            $out
        );
    }

    public function test_https_legacy_storage_url_rewrites_to_cdn_when_cloud_disk_and_cdn_set(): void
    {
        $diskName = 'r2-resolver-rewrite-test';
        config([
            'tenant_storage.public_disk' => $diskName,
            'tenant_storage.public_cdn_base_url' => 'https://cdn.example.com',
            'tenant_storage.public_url_version' => '',
        ]);

        $nonLocal = Mockery::mock(FilesystemAdapter::class);
        $fly = Mockery::mock(\League\Flysystem\FilesystemAdapter::class);
        $nonLocal->shouldReceive('getAdapter')->andReturn($fly);

        Storage::partialMock()
            ->shouldReceive('disk')
            ->andReturnUsing(function (string $name) use ($nonLocal, $diskName) {
                if ($name === $diskName) {
                    return $nonLocal;
                }

                return (new FilesystemManager(app()))->disk($name);
            });

        $legacy = 'https://tenant.example/storage/tenants/2/public/site/programs/parking/card-cover-desktop.webp?v=1';
        $out = TenantPublicAssetResolver::resolve($legacy, 2);

        $this->assertSame(
            'https://cdn.example.com/tenants/2/public/site/programs/parking/card-cover-desktop.webp?v=1',
            $out
        );
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

    public function test_legacy_expert_auto_paths_get_site_prefix_under_public(): void
    {
        config(['tenant_storage.public_cdn_base_url' => 'https://cdn.example']);

        $rel = TenantPublicAssetResolver::resolve('expert_auto/programs/single-session/card-cover-mobile.webp', 5);
        $this->assertNotNull($rel);
        $this->assertStringContainsString('tenants/5/public/site/expert_auto/programs/single-session/card-cover-mobile.webp', $rel);

        $key = 'tenants/5/public/expert_auto/programs/single-session/card-cover-mobile.webp';
        $full = TenantPublicAssetResolver::resolve($key, 5);
        $this->assertNotNull($full);
        $this->assertStringContainsString('site/expert_auto/programs/single-session/card-cover-mobile.webp', $full);
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
