<?php

namespace Tests\Unit\Tenant\StorageQuota;

use App\Models\Tenant;
use App\Models\TenantStorageQuotaEvent;
use App\Tenant\StorageQuota\StorageQuotaExceededException;
use App\Tenant\StorageQuota\TenantStorageQuotaService;
use App\Tenant\StorageQuota\TenantStorageQuotaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantStorageQuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_by_free_percent_thresholds(): void
    {
        $svc = app(TenantStorageQuotaService::class);

        $this->assertSame(TenantStorageQuotaStatus::Exceeded, $svc->computeStatus(100, 100, 20, 10));
        $this->assertSame(TenantStorageQuotaStatus::Exceeded, $svc->computeStatus(100, 101, 20, 10));
        $this->assertSame(TenantStorageQuotaStatus::Critical10, $svc->computeStatus(100, 95, 20, 10));
        $this->assertSame(TenantStorageQuotaStatus::Warning20, $svc->computeStatus(100, 85, 20, 10));
        $this->assertSame(TenantStorageQuotaStatus::Ok, $svc->computeStatus(100, 50, 20, 10));
    }

    public function test_assert_can_store_blocks_when_hard_stop_and_over_quota(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Quota test',
            'slug' => 'quota-'.Str::random(8),
            'theme_key' => 'default',
            'status' => 'trial',
        ]);
        $svc = app(TenantStorageQuotaService::class);
        $svc->ensureQuotaRecord($tenant);
        $quota = $tenant->fresh()->storageQuota;
        $quota->update([
            'base_quota_bytes' => 100,
            'extra_quota_bytes' => 0,
            'used_bytes' => 90,
            'hard_stop_enabled' => true,
        ]);

        $svc->assertCanStoreBytes($tenant->fresh(), 10, 'test');
        $this->expectException(StorageQuotaExceededException::class);
        $svc->assertCanStoreBytes($tenant->fresh(), 11, 'test');
    }

    public function test_upload_blocked_event_on_assert_failure(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Quota test 2',
            'slug' => 'quota2-'.Str::random(8),
            'theme_key' => 'default',
            'status' => 'trial',
        ]);
        $svc = app(TenantStorageQuotaService::class);
        $svc->ensureQuotaRecord($tenant);
        $tenant->storageQuota->update([
            'base_quota_bytes' => 10,
            'used_bytes' => 10,
            'hard_stop_enabled' => true,
        ]);

        try {
            $svc->assertCanStoreBytes($tenant->fresh(), 1, 'branding_upload');
        } catch (StorageQuotaExceededException) {
        }

        $this->assertDatabaseHas('tenant_storage_quota_events', [
            'tenant_id' => $tenant->id,
            'type' => 'upload_blocked_quota_exceeded',
        ]);
        $ev = TenantStorageQuotaEvent::query()->where('tenant_id', $tenant->id)->latest()->first();
        $this->assertSame('branding_upload', $ev->payload['context'] ?? null);
    }
}
