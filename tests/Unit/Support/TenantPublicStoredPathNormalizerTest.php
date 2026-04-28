<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Storage\TenantPublicStoredPathNormalizer;
use PHPUnit\Framework\TestCase;

final class TenantPublicStoredPathNormalizerTest extends TestCase
{
    public function test_returns_null_for_empty_and_http(): void
    {
        $this->assertNull(TenantPublicStoredPathNormalizer::toLogicalSitePath(''));
        $this->assertNull(TenantPublicStoredPathNormalizer::toLogicalSitePath('https://cdn.example/x.jpg'));
    }

    public function test_site_prefix_passthrough(): void
    {
        $this->assertSame(
            'site/uploads/page-builder/case-study/a.webp',
            TenantPublicStoredPathNormalizer::toLogicalSitePath('site/uploads/page-builder/case-study/a.webp'),
        );
    }

    public function test_strips_leading_slash_site(): void
    {
        $this->assertSame(
            'site/brand/x.jpg',
            TenantPublicStoredPathNormalizer::toLogicalSitePath('/site/brand/x.jpg'),
        );
    }

    public function test_extracts_after_public(): void
    {
        $this->assertSame(
            'site/brand/proof/x.jpg',
            TenantPublicStoredPathNormalizer::toLogicalSitePath('D:/storage/tenants/4/public/site/brand/proof/x.jpg'),
        );
    }

    public function test_relative_gets_brand_prefix(): void
    {
        $this->assertSame(
            'site/brand/foo.jpg',
            TenantPublicStoredPathNormalizer::toLogicalSitePath('foo.jpg'),
        );
    }
}
