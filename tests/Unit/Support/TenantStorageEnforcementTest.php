<?php

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Support\Storage\TenantStorage;
use App\Tenant\CurrentTenant;
use LogicException;
use Tests\TestCase;

class TenantStorageEnforcementTest extends TestCase
{
    public function test_throws_when_resolved_tenant_differs_from_for_id(): void
    {
        config(['tenant_storage.enforce_current_tenant_context' => true]);

        $one = new Tenant;
        $one->id = 1;
        app()->instance(CurrentTenant::class, new CurrentTenant($one, null, false));

        $this->expectException(LogicException::class);
        TenantStorage::for(2);
    }

    public function test_allows_any_id_on_non_tenant_host(): void
    {
        config(['tenant_storage.enforce_current_tenant_context' => true]);

        app()->instance(CurrentTenant::class, new CurrentTenant(null, null, true));

        $this->assertSame('tenants/9/public/x', TenantStorage::for(9)->publicPath('x'));
    }

    public function test_for_trusted_skips_guard(): void
    {
        config(['tenant_storage.enforce_current_tenant_context' => true]);

        $one = new Tenant;
        $one->id = 1;
        app()->instance(CurrentTenant::class, new CurrentTenant($one, null, false));

        $this->assertSame('tenants/2/private/a', TenantStorage::forTrusted(2)->privatePath('a'));
    }
}
