<?php

namespace Tests\Feature\Terminology;

use App\Filament\Tenant\Pages\TerminologySettings;
use App\Models\DomainLocalizationPreset;
use App\Models\DomainLocalizationPresetTerm;
use App\Models\DomainTerm;
use App\Models\Tenant;
use App\Models\TenantTermOverride;
use App\Models\User;
use App\Services\CurrentTenantManager;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class TenantTerminologySettingsHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * @return array{0: Tenant, 1: User, 2: DomainTerm}
     */
    private function tenantWithVocabulary(bool $termEditable = true): array
    {
        $preset = DomainLocalizationPreset::query()->create([
            'slug' => 'hardening_'.uniqid(),
            'name' => 'Hardening',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'T',
            'slug' => 't-'.uniqid(),
            'status' => 'active',
            'domain_localization_preset_id' => $preset->id,
        ]);

        $term = DomainTerm::query()->create([
            'term_key' => DomainTermKeys::BOOKING,
            'group' => 'booking_flow',
            'default_label' => 'System booking',
            'value_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'is_editable_by_tenant' => $termEditable,
        ]);

        DomainLocalizationPresetTerm::query()->create([
            'preset_id' => $preset->id,
            'term_id' => $term->id,
            'label' => 'Preset booking',
            'short_label' => null,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        return [$tenant, $user, $term];
    }

    private function bindTenantPanel(Tenant $tenant, User $user): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);
    }

    public function test_reset_one_removes_override_and_label_falls_back_to_preset(): void
    {
        [$tenant, $user, $term] = $this->tenantWithVocabulary(true);
        TenantTermOverride::query()->create([
            'tenant_id' => $tenant->id,
            'term_id' => $term->id,
            'label' => 'My override',
            'source' => 'manual',
        ]);
        $tenant->refresh();

        $svc = app(TenantTerminologyService::class);
        $cacheKey = $svc->dictionaryCacheKey($tenant);
        $this->assertSame('My override', $svc->label($tenant, DomainTermKeys::BOOKING));
        $this->assertTrue(Cache::has($cacheKey));

        $this->bindTenantPanel($tenant, $user);

        Livewire::test(TerminologySettings::class)
            ->callTableAction('resetOne', $term)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('tenant_term_overrides', [
            'tenant_id' => $tenant->id,
            'term_id' => $term->id,
        ]);

        $tenant->refresh();
        // Table re-render may repopulate the dictionary cache; stale override must not persist.
        $this->assertSame('Preset booking', $svc->label($tenant, DomainTermKeys::BOOKING));
        $this->assertSame('preset', $svc->dictionary($tenant)[DomainTermKeys::BOOKING]['source'] ?? '');
    }

    public function test_reset_all_removes_all_overrides_and_invalidates_cache(): void
    {
        [$tenant, $user, $term] = $this->tenantWithVocabulary(true);

        $term2 = DomainTerm::query()->create([
            'term_key' => DomainTermKeys::LEAD,
            'group' => 'crm',
            'default_label' => 'System lead',
            'value_type' => 'text',
            'is_required' => true,
            'is_active' => true,
            'is_editable_by_tenant' => true,
        ]);

        $presetId = $tenant->domain_localization_preset_id;
        DomainLocalizationPresetTerm::query()->create([
            'preset_id' => $presetId,
            'term_id' => $term2->id,
            'label' => 'Preset lead',
        ]);

        TenantTermOverride::query()->create([
            'tenant_id' => $tenant->id,
            'term_id' => $term->id,
            'label' => 'O1',
            'source' => 'manual',
        ]);
        TenantTermOverride::query()->create([
            'tenant_id' => $tenant->id,
            'term_id' => $term2->id,
            'label' => 'O2',
            'source' => 'manual',
        ]);
        $tenant->refresh();

        $svc = app(TenantTerminologyService::class);
        $cacheKey = $svc->dictionaryCacheKey($tenant);
        $svc->label($tenant, DomainTermKeys::BOOKING);
        $this->assertTrue(Cache::has($cacheKey));

        $this->bindTenantPanel($tenant, $user);

        Livewire::test(TerminologySettings::class)
            ->callTableAction('resetAll')
            ->assertHasNoErrors();

        $this->assertSame(0, TenantTermOverride::query()->where('tenant_id', $tenant->id)->count());
        $tenant->refresh();
        $this->assertSame('Preset booking', $svc->label($tenant, DomainTermKeys::BOOKING));
        $this->assertSame('Preset lead', $svc->label($tenant, DomainTermKeys::LEAD));
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_edit_label_invalidates_cached_dictionary(): void
    {
        [$tenant, $user, $term] = $this->tenantWithVocabulary(true);
        $tenant->refresh();

        $svc = app(TenantTerminologyService::class);
        $cacheKey = $svc->dictionaryCacheKey($tenant);
        $svc->label($tenant, DomainTermKeys::BOOKING);
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertSame('Preset booking', $svc->label($tenant, DomainTermKeys::BOOKING));

        $this->bindTenantPanel($tenant, $user);

        Livewire::test(TerminologySettings::class)
            ->callTableAction('editLabel', $term, [
                'label' => 'Renamed',
                'short_label' => '',
            ])
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertSame('Renamed', $svc->label($tenant, DomainTermKeys::BOOKING));
        $this->assertSame('override', $svc->dictionary($tenant)[DomainTermKeys::BOOKING]['source'] ?? '');
        $this->assertTrue(Cache::has($cacheKey));
    }
}
