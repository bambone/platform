<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantDomainDeletionRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_domain_for_tenant_cannot_be_deleted(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Solo',
            'slug' => 'solo',
            'status' => 'active',
        ]);

        $domain = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'solo.example.test',
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
        ]);

        $this->assertFalse($domain->delete());
        $this->assertTrue(TenantDomain::query()->whereKey($domain->getKey())->exists());
    }

    public function test_deleting_primary_promotes_another_domain(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Duo',
            'slug' => 'duo',
            'status' => 'active',
        ]);

        $primary = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'a.duo.example.test',
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => true,
            'status' => TenantDomain::STATUS_ACTIVE,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
        ]);

        $secondary = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'b.duo.example.test',
            'type' => TenantDomain::TYPE_SUBDOMAIN,
            'is_primary' => false,
            'status' => TenantDomain::STATUS_PENDING,
            'ssl_status' => TenantDomain::SSL_NOT_REQUIRED,
        ]);

        $this->assertTrue($primary->delete());

        $secondary->refresh();
        $this->assertTrue($secondary->is_primary);
    }
}
