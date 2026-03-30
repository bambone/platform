<?php

namespace Tests\Unit\Terminology;

use App\Models\DomainLocalizationPreset;
use App\Models\DomainLocalizationPresetTerm;
use App\Models\DomainTerm;
use App\Models\Tenant;
use App\Models\TenantTermOverride;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TenantTerminologyServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Test tenant',
            'slug' => 'test-'.uniqid(),
            'status' => 'active',
        ]);
    }

    private function seedMinimalVocabulary(Tenant $tenant): void
    {
        $preset = DomainLocalizationPreset::query()->create([
            'slug' => 'test_preset',
            'name' => 'Test',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $tenant->update(['domain_localization_preset_id' => $preset->id]);

        $term = DomainTerm::query()->create([
            'term_key' => DomainTermKeys::BOOKING,
            'group' => 'booking_flow',
            'default_label' => 'Default booking',
            'value_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'is_editable_by_tenant' => true,
        ]);

        DomainLocalizationPresetTerm::query()->create([
            'preset_id' => $preset->id,
            'term_id' => $term->id,
            'label' => 'Preset booking',
            'short_label' => 'PB',
        ]);
    }

    public function test_preset_overrides_default_label(): void
    {
        $tenant = $this->makeTenant();
        $this->seedMinimalVocabulary($tenant);
        $tenant->refresh();

        $s = app(TenantTerminologyService::class);
        $this->assertSame('Preset booking', $s->label($tenant, DomainTermKeys::BOOKING));
        $this->assertSame('PB', $s->shortLabel($tenant, DomainTermKeys::BOOKING));
    }

    public function test_tenant_override_wins_over_preset(): void
    {
        $tenant = $this->makeTenant();
        $this->seedMinimalVocabulary($tenant);
        $tenant->refresh();

        $term = DomainTerm::query()->where('term_key', DomainTermKeys::BOOKING)->firstOrFail();
        TenantTermOverride::query()->create([
            'tenant_id' => $tenant->id,
            'term_id' => $term->id,
            'label' => 'Override booking',
            'short_label' => 'OB',
            'source' => 'manual',
        ]);

        $tenant->refresh();
        $s = app(TenantTerminologyService::class);
        $this->assertSame('Override booking', $s->label($tenant, DomainTermKeys::BOOKING));
        $this->assertSame('OB', $s->shortLabel($tenant, DomainTermKeys::BOOKING));
    }

    public function test_fallback_to_default_when_no_preset_row(): void
    {
        $tenant = $this->makeTenant();
        $preset = DomainLocalizationPreset::query()->create([
            'slug' => 'empty_preset',
            'name' => 'Empty',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $tenant->update(['domain_localization_preset_id' => $preset->id]);

        DomainTerm::query()->create([
            'term_key' => DomainTermKeys::BOOKING,
            'group' => 'booking_flow',
            'default_label' => 'Only default',
            'value_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'is_editable_by_tenant' => true,
        ]);

        $tenant->refresh();
        $s = app(TenantTerminologyService::class);
        $this->assertSame('Only default', $s->label($tenant, DomainTermKeys::BOOKING));
    }

    public function test_unknown_term_key_returns_key(): void
    {
        $tenant = $this->makeTenant();
        $this->seedMinimalVocabulary($tenant);
        $tenant->refresh();

        $s = app(TenantTerminologyService::class);
        $this->assertSame('no.such.term', $s->label($tenant, 'no.such.term'));
    }

    public function test_many_returns_labels_for_keys(): void
    {
        $tenant = $this->makeTenant();
        $this->seedMinimalVocabulary($tenant);
        DomainTerm::query()->create([
            'term_key' => DomainTermKeys::LEAD,
            'group' => 'crm',
            'default_label' => 'Lead default',
            'value_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'is_editable_by_tenant' => true,
        ]);

        $tenant->refresh();
        $s = app(TenantTerminologyService::class);
        $m = $s->many($tenant, [DomainTermKeys::BOOKING, DomainTermKeys::LEAD]);
        $this->assertSame('Preset booking', $m[DomainTermKeys::BOOKING]);
        $this->assertSame('Lead default', $m[DomainTermKeys::LEAD]);
    }

    public function test_cache_is_used_until_invalidated(): void
    {
        $tenant = $this->makeTenant();
        $this->seedMinimalVocabulary($tenant);
        $tenant->refresh();

        $s = app(TenantTerminologyService::class);
        $key = $s->dictionaryCacheKey($tenant);

        $this->assertFalse(Cache::has($key));
        $s->label($tenant, DomainTermKeys::BOOKING);
        $this->assertTrue(Cache::has($key));

        $term = DomainTerm::query()->where('term_key', DomainTermKeys::BOOKING)->firstOrFail();
        TenantTermOverride::query()->create([
            'tenant_id' => $tenant->id,
            'term_id' => $term->id,
            'label' => 'After cache',
            'source' => 'manual',
        ]);

        $this->assertFalse(Cache::has($key));
        $this->assertSame('After cache', $s->label($tenant, DomainTermKeys::BOOKING));
    }

    public function test_mass_delete_override_does_not_dispatch_model_events_cache_stale_until_forget(): void
    {
        $tenant = $this->makeTenant();
        $this->seedMinimalVocabulary($tenant);
        $tenant->refresh();

        $term = DomainTerm::query()->where('term_key', DomainTermKeys::BOOKING)->firstOrFail();
        TenantTermOverride::query()->create([
            'tenant_id' => $tenant->id,
            'term_id' => $term->id,
            'label' => 'Override booking',
            'source' => 'manual',
        ]);

        $s = app(TenantTerminologyService::class);
        $key = $s->dictionaryCacheKey($tenant);
        $this->assertSame('Override booking', $s->label($tenant, DomainTermKeys::BOOKING));
        $this->assertTrue(Cache::has($key));

        TenantTermOverride::query()
            ->where('tenant_id', $tenant->id)
            ->where('term_id', $term->id)
            ->delete();

        $this->assertTrue(Cache::has($key));
        $this->assertSame('Override booking', $s->label($tenant, DomainTermKeys::BOOKING));

        $s->forgetTenant($tenant->id);
        $this->assertFalse(Cache::has($key));
        $this->assertSame('Preset booking', $s->label($tenant, DomainTermKeys::BOOKING));
    }

    public function test_inactive_term_not_in_dictionary_falls_back_to_key(): void
    {
        $tenant = $this->makeTenant();
        $this->seedMinimalVocabulary($tenant);
        $term = DomainTerm::query()->where('term_key', DomainTermKeys::BOOKING)->firstOrFail();
        $term->update(['is_active' => false]);

        $tenant->refresh();
        app(TenantTerminologyService::class)->forgetTenant($tenant->id);

        $s = app(TenantTerminologyService::class);
        $this->assertSame(DomainTermKeys::BOOKING, $s->label($tenant, DomainTermKeys::BOOKING));
    }
}
