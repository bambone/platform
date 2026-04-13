<?php

namespace Tests\Unit\Support;

use App\Support\Storage\TenantPublicObjectKey;
use InvalidArgumentException;
use Tests\TestCase;

class TenantPublicObjectKeyTest extends TestCase
{
    public function test_normalize_trims_and_normalizes_slashes(): void
    {
        $this->assertSame(
            'tenants/1/public/site/x.png',
            TenantPublicObjectKey::normalize('  tenants/1/public/site/x.png  ')
        );
    }

    public function test_normalize_rejects_empty_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TenantPublicObjectKey::normalize('');
    }

    public function test_normalize_rejects_leading_slash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TenantPublicObjectKey::normalize('/tenants/1/public/x');
    }

    public function test_normalize_rejects_dotdot_in_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TenantPublicObjectKey::normalize('tenants/1/public/../etc/passwd');
    }

    public function test_assert_web_exposed_requires_matching_tenant_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TenantPublicObjectKey::assertWebExposedTenantPublicKey('tenants/2/public/x', 1);
    }

    public function test_is_web_exposed_returns_false_for_healthcheck_style_prefix(): void
    {
        $this->assertFalse(TenantPublicObjectKey::isWebExposedTenantPublicKey('healthchecks/ping', 1));
    }
}
