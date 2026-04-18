<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Tenant;
use App\Support\TenantSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_normalized_slug_taken_matches_legacy_mixed_case_in_database(): void
    {
        Tenant::query()->create([
            'name' => 'Legacy',
            'slug' => 'My-Client-Slug',
            'status' => 'active',
        ]);

        $this->assertTrue(TenantSlug::isNormalizedSlugTaken('my-client-slug'));
    }

    public function test_is_normalized_slug_taken_respects_ignore_tenant_id(): void
    {
        $t = Tenant::query()->create([
            'name' => 'A',
            'slug' => 'same-slug',
            'status' => 'active',
        ]);

        $this->assertFalse(TenantSlug::isNormalizedSlugTaken('same-slug', $t->id));
    }
}
