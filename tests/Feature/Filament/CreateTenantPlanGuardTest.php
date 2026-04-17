<?php

namespace Tests\Feature\Filament;

use App\Filament\Platform\Resources\TenantResource\Pages\CreateTenant;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Support\Exceptions\Halt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class CreateTenantPlanGuardTest extends TestCase
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

    public function test_mutate_fills_plan_from_default_when_empty(): void
    {
        $this->seed(PlanSeeder::class);
        $expected = Plan::defaultIdForOnboarding();
        $this->assertNotNull($expected);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        $component = Livewire::test(CreateTenant::class)->instance();
        $method = new ReflectionMethod(CreateTenant::class, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $out = $method->invoke($component, [
            'name' => 'ACME',
            'slug' => 'acme-guard',
            'plan_id' => null,
        ]);

        $this->assertSame($expected, $out['plan_id']);
    }

    public function test_mutate_throws_halt_when_no_active_plans(): void
    {
        $this->seed(PlanSeeder::class);
        Plan::query()->update(['is_active' => false]);

        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        $component = Livewire::test(CreateTenant::class)->instance();
        $method = new ReflectionMethod(CreateTenant::class, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $this->expectException(Halt::class);
        $method->invoke($component, [
            'name' => 'No plan',
            'slug' => 'no-plan-tenant',
            'plan_id' => null,
        ]);
    }
}
