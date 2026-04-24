<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TenantFiles\TenantPublicFileReferenceFinder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Фиксирует контракт: какие форматы путей ищет guard перед delete.
 */
final class TenantPublicFileReferenceFinderNeedlesTest extends TestCase
{
    #[Test]
    public function needle_variants_for_standard_object_key_include_full_rel_public_and_leading_slash(): void
    {
        $tid = 42;
        $key = "tenants/{$tid}/public/site/brand/logo.png";
        $finder = new TenantPublicFileReferenceFinder;
        $v = $finder->needleVariantsForObjectKey($tid, $key);

        $this->assertContains($key, $v);
        $this->assertContains('site/brand/logo.png', $v);
        $this->assertContains('public/site/brand/logo.png', $v);
        $this->assertContains('/site/brand/logo.png', $v);
        $this->assertContains(str_replace('/', '\\/', $key), $v);
        $this->assertContains(str_replace('/', '\\/', 'site/brand/logo.png'), $v);
    }
}
