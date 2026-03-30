<?php

namespace Tests\Unit\Terminology;

use App\Models\DomainTerm;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TenantTerminologyGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_locked_term_aborts_403(): void
    {
        $term = DomainTerm::query()->create([
            'term_key' => DomainTermKeys::BOOKING,
            'group' => 'booking_flow',
            'default_label' => 'X',
            'value_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'is_editable_by_tenant' => false,
        ]);

        try {
            TenantTerminologyGuard::assertTermEditableByTenant($term);
            $this->fail('Expected HTTP 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_editable_term_does_not_abort(): void
    {
        $term = DomainTerm::query()->create([
            'term_key' => DomainTermKeys::BOOKING,
            'group' => 'booking_flow',
            'default_label' => 'X',
            'value_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'is_editable_by_tenant' => true,
        ]);

        TenantTerminologyGuard::assertTermEditableByTenant($term);
        $this->assertTrue(true);
    }
}
