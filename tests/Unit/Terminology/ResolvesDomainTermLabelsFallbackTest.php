<?php

namespace Tests\Unit\Terminology;

use App\Filament\Tenant\Concerns\ResolvesDomainTermLabels;
use App\Tenant\CurrentTenant;
use App\Terminology\DomainTermKeys;
use Tests\TestCase;

class ResolvesDomainTermLabelsFallbackTest extends TestCase
{
    public function test_domain_term_label_returns_fallback_when_tenant_context_missing(): void
    {
        app()->instance(CurrentTenant::class, new CurrentTenant(null, null, false, null));

        $probe = new class
        {
            use ResolvesDomainTermLabels;

            public function run(): string
            {
                return static::domainTermLabel(DomainTermKeys::BOOKING, 'Fallback booking');
            }
        };

        $this->assertSame('Fallback booking', $probe->run());
    }
}
