<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\TenantServiceProgram;
use App\Tenant\Expert\ServiceProgramType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TenantServiceProgramSlugLengthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function saving_rejects_slug_longer_than_public_inquiry_limit(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 't-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $long = str_repeat('a', TenantServiceProgram::SLUG_MAX_LENGTH + 1);
        $this->expectException(\InvalidArgumentException::class);
        TenantServiceProgram::query()->create([
            'tenant_id' => (int) $tenant->id,
            'slug' => $long,
            'title' => 'X',
            'teaser' => '',
            'description' => '',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ]);
    }

    #[Test]
    public function saving_accepts_slug_at_max_length(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T2',
            'slug' => 't2-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $slug = str_repeat('b', TenantServiceProgram::SLUG_MAX_LENGTH);
        $p = TenantServiceProgram::query()->create([
            'tenant_id' => (int) $tenant->id,
            'slug' => $slug,
            'title' => 'OK',
            'teaser' => '',
            'description' => '',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ]);
        $this->assertSame($slug, $p->slug);
    }

    #[Test]
    public function saving_rejects_slug_that_normalizes_to_empty(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T3',
            'slug' => 't3-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $this->expectException(\InvalidArgumentException::class);
        TenantServiceProgram::query()->create([
            'tenant_id' => (int) $tenant->id,
            'slug' => '###',
            'title' => 'X',
            'teaser' => '',
            'description' => '',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ]);
    }

    #[Test]
    public function is_public_inquiry_slug_format_rejects_mixed_case_without_normalization(): void
    {
        $this->assertTrue(TenantServiceProgram::isPublicInquirySlugFormat('ppf'));
        $this->assertFalse(TenantServiceProgram::isPublicInquirySlugFormat('Ppf'));
    }

    #[Test]
    public function saving_rejects_duplicate_slug_in_same_tenant(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'T4',
            'slug' => 't4-'.substr(uniqid(), -6),
            'theme_key' => 'black_duck',
            'currency' => 'RUB',
            'status' => 'active',
        ]);
        $payload = [
            'tenant_id' => (int) $tenant->id,
            'slug' => 'ppf',
            'title' => 'A',
            'teaser' => '',
            'description' => '',
            'program_type' => ServiceProgramType::Program->value,
            'is_visible' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
        TenantServiceProgram::query()->create($payload);
        $this->expectException(\InvalidArgumentException::class);
        TenantServiceProgram::query()->create($payload);
    }

    #[Test]
    public function normalize_public_slug_trims_and_slugifies(): void
    {
        $this->assertSame('ppf-premium', TenantServiceProgram::normalizePublicSlugForStorage('  PPF Premium  '));
    }
}
