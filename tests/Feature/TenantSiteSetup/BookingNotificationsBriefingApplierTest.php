<?php

namespace Tests\Feature\TenantSiteSetup;

use App\Models\BookingSettingsPreset;
use App\Models\NotificationDestination;
use App\Models\NotificationSubscription;
use App\Models\User;
use App\Tenant\CurrentTenant;
use App\TenantSiteSetup\BookingNotificationsBriefingApplier;
use App\TenantSiteSetup\BookingNotificationsBriefingWizardMarkers;
use App\TenantSiteSetup\BookingNotificationsQuestionnaireRepository;
use App\TenantSiteSetup\SetupCompletionEvaluator;
use App\TenantSiteSetup\SetupProfileRepository;
use App\TenantSiteSetup\TenantOnboardingBranchId;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class BookingNotificationsBriefingApplierTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_apply_creates_preset_destinations_and_subscriptions(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_brief', ['theme_key' => 'expert_auto', 'scheduling_module_enabled' => true]);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        $profiles = app(SetupProfileRepository::class);
        $profiles->save($tenant->id, array_merge($profiles->getMerged($tenant->id), [
            'primary_goal' => 'booking',
        ]));

        $data = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);
        $data['dest_email'] = 'ops@example.test';
        $data['events_enabled'] = ['crm_request.created'];

        $result = app(BookingNotificationsBriefingApplier::class)->apply($tenant, $user, $data);

        $this->assertSame(1, $result['destinations_created']);
        $this->assertSame(1, $result['subscriptions_created']);
        $this->assertNotNull($result['preset_id']);

        $this->assertTrue(
            BookingSettingsPreset::query()->where('tenant_id', $tenant->id)->where('name', BookingNotificationsBriefingWizardMarkers::PRESET_NAME)->exists()
        );
        $this->assertTrue(
            NotificationDestination::query()->where('tenant_id', $tenant->id)->where('name', BookingNotificationsBriefingWizardMarkers::DEST_EMAIL_NAME)->exists()
        );
        $this->assertTrue(
            NotificationSubscription::query()->where('tenant_id', $tenant->id)->where('event_key', 'crm_request.created')->exists()
        );

        $this->assertTrue(
            app(SetupCompletionEvaluator::class)->isComplete($tenant, \App\TenantSiteSetup\SetupItemRegistry::definitions()['setup.booking_notifications_brief'])
        );
    }

    public function test_crm_only_branch_skips_preset_and_booking_subscriptions_when_scheduling_module_on(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_crm_only', ['theme_key' => 'expert_auto', 'scheduling_module_enabled' => true]);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        $profiles = app(SetupProfileRepository::class);
        $profiles->save($tenant->id, array_merge($profiles->getMerged($tenant->id), [
            'primary_goal' => 'booking',
            'desired_branch' => TenantOnboardingBranchId::CrmOnly->value,
        ]));

        $data = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);
        $data['dest_email'] = 'crmonly@example.test';
        $data['events_enabled'] = ['crm_request.created', 'booking.created'];

        $result = app(BookingNotificationsBriefingApplier::class)->apply($tenant, $user, $data);

        $this->assertNull($result['preset_id']);
        $this->assertFalse(
            BookingSettingsPreset::query()->where('tenant_id', $tenant->id)->where('name', BookingNotificationsBriefingWizardMarkers::PRESET_NAME)->exists()
        );
        $this->assertTrue(
            NotificationSubscription::query()->where('tenant_id', $tenant->id)->where('event_key', 'crm_request.created')->exists()
        );
        $this->assertFalse(
            NotificationSubscription::query()->where('tenant_id', $tenant->id)->where('event_key', 'booking.created')->exists()
        );
    }

    public function test_slot_booking_desired_module_off_effective_crm_only_strips_booking_subscriptions(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_mod_off', ['theme_key' => 'expert_auto', 'scheduling_module_enabled' => false]);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        $profiles = app(SetupProfileRepository::class);
        $profiles->save($tenant->id, array_merge($profiles->getMerged($tenant->id), [
            'primary_goal' => 'booking',
            'desired_branch' => TenantOnboardingBranchId::SlotBooking->value,
        ]));

        $data = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);
        $data['dest_email'] = 'modoff@example.test';
        $data['events_enabled'] = ['crm_request.created', 'booking.created'];

        $result = app(BookingNotificationsBriefingApplier::class)->apply($tenant, $user, $data);

        $this->assertNull($result['preset_id']);
        $this->assertTrue(
            NotificationSubscription::query()->where('tenant_id', $tenant->id)->where('event_key', 'crm_request.created')->exists()
        );
        $this->assertFalse(
            NotificationSubscription::query()->where('tenant_id', $tenant->id)->where('event_key', 'booking.created')->exists()
        );
    }

    public function test_mixed_branch_applies_booking_automation_when_module_on(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_mixed', ['theme_key' => 'expert_auto', 'scheduling_module_enabled' => true]);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        $profiles = app(SetupProfileRepository::class);
        $profiles->save($tenant->id, array_merge($profiles->getMerged($tenant->id), [
            'primary_goal' => 'booking',
            'desired_branch' => TenantOnboardingBranchId::Mixed->value,
        ]));

        $data = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);
        $data['dest_email'] = 'mixed@example.test';
        $data['events_enabled'] = ['booking.created'];

        $result = app(BookingNotificationsBriefingApplier::class)->apply($tenant, $user, $data);

        $this->assertNotNull($result['preset_id']);
        $this->assertTrue(
            NotificationSubscription::query()->where('tenant_id', $tenant->id)->where('event_key', 'booking.created')->exists()
        );
    }

    public function test_fallback_primary_goal_booking_without_desired_branch_creates_preset(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_fallback', ['theme_key' => 'expert_auto', 'scheduling_module_enabled' => true]);
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_owner', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $domain = $tenant->domains()->where('is_primary', true)->first();
        $this->app->instance(
            CurrentTenant::class,
            new CurrentTenant($tenant, $domain, false, $this->tenancyHostForSlug((string) $tenant->slug))
        );
        $this->actingAs($user);

        $profiles = app(SetupProfileRepository::class);
        $profiles->save($tenant->id, array_merge($profiles->getMerged($tenant->id), [
            'primary_goal' => 'booking',
            'desired_branch' => '',
        ]));

        $data = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);
        $data['dest_email'] = 'fallback@example.test';
        $data['events_enabled'] = ['crm_request.created'];

        $result = app(BookingNotificationsBriefingApplier::class)->apply($tenant, $user, $data);

        $this->assertNotNull($result['preset_id']);
    }
}
