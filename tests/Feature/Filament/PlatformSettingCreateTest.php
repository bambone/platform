<?php

namespace Tests\Feature\Filament;

use App\Filament\Platform\Resources\PlatformSettingResource\Pages\CreatePlatformSetting;
use App\Filament\Platform\Resources\PlatformSettingResource\Pages\EditPlatformSetting;
use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class PlatformSettingCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_mutate_form_data_maps_registry_key_to_database_key(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        $component = Livewire::test(CreatePlatformSetting::class)->instance();

        $mutate = new ReflectionMethod(CreatePlatformSetting::class, 'mutateFormDataBeforeCreate');
        $mutate->setAccessible(true);

        $out = $mutate->invoke($component, [
            'use_custom_key' => false,
            'registry_key' => 'platform_support_email',
            'type' => 'string',
            'value' => 'support@example.test',
        ]);

        $this->assertSame('platform_support_email', $out['key']);
        $this->assertSame('string', $out['type']);
        $this->assertSame('support@example.test', $out['value']);
        $this->assertArrayNotHasKey('registry_key', $out);
    }

    public function test_create_from_registry_persists_row_with_key(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(CreatePlatformSetting::class)
            ->fillForm([
                'use_custom_key' => false,
                'registry_key' => 'platform_support_email',
                'type' => 'string',
                'value' => 'support@example.test',
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'platform_support_email',
            'type' => 'string',
            'value' => 'support@example.test',
        ]);
    }

    public function test_create_with_custom_key_persists_row(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(CreatePlatformSetting::class)
            ->fillForm([
                'use_custom_key' => true,
                'key' => 'integration.custom_flag',
                'type' => 'boolean',
                'value_boolean' => true,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'integration.custom_flag',
            'type' => 'boolean',
            'value' => '1',
        ]);
    }

    public function test_mutate_empty_registry_shows_error_on_registry_key(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        $component = Livewire::test(CreatePlatformSetting::class)->instance();
        $mutate = new ReflectionMethod(CreatePlatformSetting::class, 'mutateFormDataBeforeCreate');
        $mutate->setAccessible(true);

        try {
            $mutate->invoke($component, [
                'use_custom_key' => false,
                'registry_key' => '',
                'type' => 'string',
                'value' => 'x',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('registry_key', $e->errors());
            $this->assertArrayNotHasKey('key', $e->errors());
        }
    }

    public function test_mutate_empty_custom_key_shows_error_on_key(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        $component = Livewire::test(CreatePlatformSetting::class)->instance();
        $mutate = new ReflectionMethod(CreatePlatformSetting::class, 'mutateFormDataBeforeCreate');
        $mutate->setAccessible(true);

        try {
            $mutate->invoke($component, [
                'use_custom_key' => true,
                'key' => '',
                'type' => 'string',
                'value' => 'x',
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('key', $e->errors());
            $this->assertArrayNotHasKey('registry_key', $e->errors());
        }
    }

    public function test_edit_hydrates_boolean_toggle_when_value_is_one(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $setting = PlatformSetting::query()->create([
            'key' => 'maintenance_mode',
            'type' => 'boolean',
            'value' => '1',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(EditPlatformSetting::class, ['record' => $setting->getKey()])
            ->assertFormSet([
                'key' => 'maintenance_mode',
                'type' => 'boolean',
                'value_boolean' => true,
            ]);
    }

    public function test_edit_hydrates_boolean_toggle_when_value_is_zero(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('platform_owner');

        $setting = PlatformSetting::query()->create([
            'key' => 'maintenance_mode',
            'type' => 'boolean',
            'value' => '0',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('platform'));
        $this->actingAs($user);

        Livewire::test(EditPlatformSetting::class, ['record' => $setting->getKey()])
            ->assertFormSet([
                'key' => 'maintenance_mode',
                'type' => 'boolean',
                'value_boolean' => false,
            ]);
    }
}
