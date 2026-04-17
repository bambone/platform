<?php

namespace Tests\Feature\TenantPush;

use App\Filament\Platform\Pages\TenantsPushPwaPage;
use App\Models\User;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushOverride;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class TenantsPushPwaPageQuickActionsTest extends TestCase
{
    use CreatesTenantsWithDomains;
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

    public function test_platform_quick_action_force_enable_updates_settings(): void
    {
        $this->seed(PlanSeeder::class);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $tenant = $this->createTenantWithActiveDomain('qaction');
        $gate = app(TenantPushFeatureGate::class);
        $s = $gate->ensureSettings($tenant);
        $s->push_override = TenantPushOverride::InheritPlan->value;
        $s->save();

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(TenantsPushPwaPage::class)
            ->call('platformQuickAction', $tenant->id, 'force_enable');

        $this->assertSame(TenantPushOverride::ForceEnable->value, $gate->findSettings($tenant)?->push_override);
    }
}
