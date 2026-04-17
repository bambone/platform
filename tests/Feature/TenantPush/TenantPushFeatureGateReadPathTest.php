<?php

namespace Tests\Feature\TenantPush;

use App\Models\TenantPushSettings;
use App\TenantPush\TenantPushFeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantPushFeatureGateReadPathTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_evaluate_does_not_insert_row_when_push_settings_missing(): void
    {
        $tenant = $this->createTenantWithActiveDomain('readpath');

        TenantPushSettings::query()->where('tenant_id', $tenant->id)->delete();

        $countBefore = TenantPushSettings::query()->count();
        app(TenantPushFeatureGate::class)->evaluate($tenant);
        $countAfter = TenantPushSettings::query()->count();

        $this->assertSame($countBefore, $countAfter);
        $this->assertNull(TenantPushSettings::query()->where('tenant_id', $tenant->id)->first());
    }

    public function test_ensure_settings_creates_row_when_missing(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ensurepath');

        TenantPushSettings::query()->where('tenant_id', $tenant->id)->delete();
        $this->assertNull(TenantPushSettings::query()->where('tenant_id', $tenant->id)->first());

        $s = app(TenantPushFeatureGate::class)->ensureSettings($tenant);
        $this->assertSame($tenant->id, $s->tenant_id);
        $this->assertNotNull(TenantPushSettings::query()->where('tenant_id', $tenant->id)->first());
    }
}
