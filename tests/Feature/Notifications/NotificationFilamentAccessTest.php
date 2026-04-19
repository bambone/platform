<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationDelivery;
use App\Models\NotificationDestination;
use App\Models\NotificationEvent;
use App\Models\PlatformSetting;
use App\Models\User;
use App\NotificationCenter\NotificationChannelType;
use App\NotificationCenter\NotificationDestinationStatus;
use App\NotificationCenter\NotificationPayloadDto;
use App\Services\CurrentTenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class NotificationFilamentAccessTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        PlatformSetting::query()->where('key', 'tenant_pivot_permission_matrix')->delete();
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    protected function getWithHost(string $host, string $path): TestResponse
    {
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $this->call('GET', 'http://'.$host.$path);
    }

    public function test_tenant_owner_can_open_notification_destinations_index(): void
    {
        $tenant = $this->createTenantWithActiveDomain('nfown');
        $host = $this->tenancyHostForSlug('nfown');
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($user)
            ->getWithHost($host, '/admin/notification-destinations')
            ->assertOk();
    }

    public function test_operator_without_notification_abilities_gets_forbidden_on_destinations(): void
    {
        $tenant = $this->createTenantWithActiveDomain('nfop');
        $host = $this->tenancyHostForSlug('nfop');
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'operator', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($user)
            ->getWithHost($host, '/admin/notification-destinations')
            ->assertForbidden();
    }

    public function test_operator_can_open_notification_browser_settings_page(): void
    {
        $tenant = $this->createTenantWithActiveDomain('nfob');
        $host = $this->tenancyHostForSlug('nfob');
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'operator', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($user)
            ->getWithHost($host, '/admin/notification-subscriptions/browser')
            ->assertOk();
    }

    public function test_operator_without_manage_settings_gets_forbidden_on_storage_monitoring(): void
    {
        $tenant = $this->createTenantWithActiveDomain('nfsm');
        $host = $this->tenancyHostForSlug('nfsm');
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'operator', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($user)
            ->getWithHost($host, '/admin/storage-monitoring')
            ->assertForbidden();
    }

    public function test_notification_destination_policy_personal_vs_shared_for_limited_role(): void
    {
        $tenant = $this->createTenantWithActiveDomain('nfpol');
        PlatformSetting::set('tenant_pivot_permission_matrix', [
            'operator' => ['manage_notification_destinations'],
        ], 'json');

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'operator', 'status' => 'active']);

        app(CurrentTenantManager::class)->setTenant($tenant);

        $shared = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'Shared',
            'type' => NotificationChannelType::InApp->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => [],
        ]);

        $personal = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'name' => 'Mine',
            'type' => NotificationChannelType::InApp->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => false,
            'config_json' => [],
        ]);

        $this->assertFalse(Gate::forUser($user)->allows('view', $shared));
        $this->assertTrue(Gate::forUser($user)->allows('view', $personal));
    }

    public function test_notification_delivery_update_denied_read_only_history(): void
    {
        $tenant = $this->createTenantWithActiveDomain('nfdel');
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        app(CurrentTenantManager::class)->setTenant($tenant);

        $event = NotificationEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => null,
            'payload_json' => (new NotificationPayloadDto('a', 'b', null, null, []))->toArray(),
            'occurred_at' => now(),
        ]);
        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'In-app',
            'type' => NotificationChannelType::InApp->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => [],
        ]);
        $delivery = NotificationDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'destination_id' => $dest->id,
            'channel_type' => $dest->type,
            'status' => 'delivered',
        ]);

        $this->assertFalse(Gate::forUser($user)->allows('update', $delivery));
    }
}
