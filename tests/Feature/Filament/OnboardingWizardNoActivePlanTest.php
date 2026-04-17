<?php

namespace Tests\Feature\Filament;

use App\Filament\Platform\Pages\OnboardingWizard;
use App\Models\DomainLocalizationPreset;
use App\Models\Plan;
use App\Models\TemplatePreset;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TemplatePresetSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingWizardNoActivePlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_create_aborts_when_no_active_plan_and_does_not_create_tenant(): void
    {
        $this->seed(PlanSeeder::class);
        $this->seed(TemplatePresetSeeder::class);

        Plan::query()->update(['is_active' => false]);

        $preset = TemplatePreset::query()->where('is_active', true)->firstOrFail();
        $locId = DomainLocalizationPreset::query()->where('slug', 'generic_services')->value('id');
        $this->assertNotNull($locId);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        $slug = 'nobody-'.uniqid('', false);

        Livewire::test(OnboardingWizard::class)
            ->set('data.name', 'No Plan Co')
            ->set('data.slug', $slug)
            ->set('data.template_preset_id', $preset->id)
            ->set('data.domain_localization_preset_id', (int) $locId)
            ->call('create');

        $this->assertFalse(Tenant::query()->where('slug', $slug)->exists());
    }
}
