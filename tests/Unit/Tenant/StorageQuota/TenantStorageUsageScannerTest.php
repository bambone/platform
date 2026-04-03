<?php

namespace Tests\Unit\Tenant\StorageQuota;

use App\Tenant\StorageQuota\TenantStorageUsageScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantStorageUsageScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sums_bytes_under_prefix_when_parent_directory_has_no_placeholder_object(): void
    {
        Storage::fake('quota-scan-public');
        Storage::fake('quota-scan-private');
        config([
            'tenant_storage.public_disk' => 'quota-scan-public',
            'tenant_storage.private_disk' => 'quota-scan-private',
        ]);

        Storage::disk('quota-scan-public')->put('tenants/7/public/media/x.bin', 'abcd');
        Storage::disk('quota-scan-private')->put('tenants/7/private/seo/y.txt', '12');

        $result = app(TenantStorageUsageScanner::class)->scan(7);

        $this->assertSame(4 + 2, $result->totalBytes);
        $this->assertSame(4, $result->publicBytes);
        $this->assertSame(2, $result->privateBytes);
        $this->assertSame(2, $result->objectCount);
    }
}
