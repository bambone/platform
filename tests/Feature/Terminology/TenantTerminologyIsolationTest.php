<?php

namespace Tests\Feature\Terminology;

use App\Models\DomainLocalizationPreset;
use App\Models\DomainLocalizationPresetTerm;
use App\Models\DomainTerm;
use App\Models\Tenant;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTerminologyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_parallel_tenants_resolve_distinct_booking_labels_without_cache_mixing(): void
    {
        $presetA = DomainLocalizationPreset::query()->create([
            'slug' => 'p_a_'.uniqid(),
            'name' => 'A',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $presetB = DomainLocalizationPreset::query()->create([
            'slug' => 'p_b_'.uniqid(),
            'name' => 'B',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $term = DomainTerm::query()->create([
            'term_key' => DomainTermKeys::BOOKING,
            'group' => 'booking_flow',
            'default_label' => 'Booking default',
            'value_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'is_editable_by_tenant' => true,
        ]);

        DomainLocalizationPresetTerm::query()->create([
            'preset_id' => $presetA->id,
            'term_id' => $term->id,
            'label' => 'Сессия',
        ]);
        DomainLocalizationPresetTerm::query()->create([
            'preset_id' => $presetB->id,
            'term_id' => $term->id,
            'label' => 'Заказ',
        ]);

        $tenantA = Tenant::query()->create([
            'name' => 'A',
            'slug' => 'ta-'.uniqid(),
            'domain_localization_preset_id' => $presetA->id,
        ]);
        $tenantB = Tenant::query()->create([
            'name' => 'B',
            'slug' => 'tb-'.uniqid(),
            'domain_localization_preset_id' => $presetB->id,
        ]);

        $svc = app(TenantTerminologyService::class);

        $this->assertSame('Сессия', $svc->label($tenantA, DomainTermKeys::BOOKING));
        $this->assertSame('Заказ', $svc->label($tenantB, DomainTermKeys::BOOKING));

        // Warm cache for both, then assert again (different cache keys per tenant)
        $svc->label($tenantA, DomainTermKeys::BOOKING);
        $svc->label($tenantB, DomainTermKeys::BOOKING);
        $this->assertSame('Сессия', $svc->label($tenantA, DomainTermKeys::BOOKING));
        $this->assertSame('Заказ', $svc->label($tenantB, DomainTermKeys::BOOKING));
    }
}
