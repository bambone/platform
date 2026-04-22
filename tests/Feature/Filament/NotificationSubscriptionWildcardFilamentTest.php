<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Tenant\Resources\NotificationSubscriptionResource\Pages\CreateNotificationSubscription;
use App\Filament\Tenant\Resources\NotificationSubscriptionResource\Pages\EditNotificationSubscription;
use App\Models\NotificationSubscription;
use App\Models\User;
use App\NotificationCenter\NotificationEventRegistry;
use App\Services\CurrentTenantManager;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

/**
 * Сквозная проверка: выбор «Все уведомления» в UI Filament сохраняет wildcard в БД.
 */
final class NotificationSubscriptionWildcardFilamentTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_create_persists_wildcard_event_key_from_form(): void
    {
        $tenant = $this->createTenantWithActiveDomain('fil_notif_wild');
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(CreateNotificationSubscription::class)
            ->fillForm([
                'name' => 'All events',
                'event_key' => NotificationEventRegistry::WILDCARD_EVENT_KEY,
                'enabled' => true,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notification_subscriptions', [
            'tenant_id' => $tenant->id,
            'name' => 'All events',
            'event_key' => '*',
        ]);
    }

    public function test_edit_persists_change_to_wildcard_event_key(): void
    {
        $tenant = $this->createTenantWithActiveDomain('fil_notif_wild_ed');
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        $sub = NotificationSubscription::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'One event',
            'event_key' => 'lead.created',
            'created_by_user_id' => $user->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($user);
        app(CurrentTenantManager::class)->setTenant($tenant);

        Livewire::test(EditNotificationSubscription::class, ['record' => $sub->getKey()])
            ->fillForm([
                'name' => 'All events',
                'event_key' => NotificationEventRegistry::WILDCARD_EVENT_KEY,
                'enabled' => true,
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notification_subscriptions', [
            'id' => $sub->id,
            'event_key' => '*',
        ]);
    }
}
