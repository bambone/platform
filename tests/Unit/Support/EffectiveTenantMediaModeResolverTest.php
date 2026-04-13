<?php

namespace Tests\Unit\Support;

use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Support\Storage\EffectiveTenantMediaModeResolver;
use App\Support\Storage\MediaDeliveryMode;
use App\Support\Storage\MediaWriteMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EffectiveTenantMediaModeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_write_mode_falls_back_to_config_when_no_tenant_and_no_platform_row(): void
    {
        config(['tenant_storage.media_write_mode_default' => 'r2_only']);

        $r = app(EffectiveTenantMediaModeResolver::class);

        $this->assertSame(MediaWriteMode::R2Only, $r->effectiveWriteMode(null));
    }

    public function test_tenant_override_beats_platform_and_config(): void
    {
        PlatformSetting::set('media.write_mode_default', 'r2_only');
        config(['tenant_storage.media_write_mode_default' => 'local_only']);

        $tenant = Tenant::query()->create([
            'name' => 'M',
            'slug' => 'm-'.Str::random(8),
            'theme_key' => 'moto',
            'status' => 'trial',
            'media_write_mode_override' => 'dual',
            'media_delivery_mode_override' => null,
        ]);

        $r = app(EffectiveTenantMediaModeResolver::class);

        $this->assertSame(MediaWriteMode::Dual, $r->effectiveWriteMode($tenant));
    }

    public function test_platform_setting_beats_config_for_delivery(): void
    {
        PlatformSetting::set('media.delivery_mode_default', 'local');
        config(['tenant_storage.media_delivery_mode_default' => 'r2']);

        $r = app(EffectiveTenantMediaModeResolver::class);

        $this->assertSame(MediaDeliveryMode::Local, $r->effectiveDeliveryMode(null));
    }
}
