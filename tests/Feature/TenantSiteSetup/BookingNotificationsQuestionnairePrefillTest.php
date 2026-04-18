<?php

declare(strict_types=1);

namespace Tests\Feature\TenantSiteSetup;

use App\Models\BookingSettingsPreset;
use App\Models\NotificationDestination;
use App\Models\NotificationSubscription;
use App\Models\TenantSetting;
use App\NotificationCenter\NotificationChannelType;
use App\NotificationCenter\NotificationDestinationStatus;
use App\TenantSiteSetup\BookingNotificationsBriefingWizardMarkers;
use App\TenantSiteSetup\BookingNotificationsQuestionnaireRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class BookingNotificationsQuestionnairePrefillTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_meta_brand_name_prefills_from_general_site_name_when_questionnaire_empty(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_prefill_site', ['name' => 'Игнорируем для теста']);

        TenantSetting::setForTenant($tenant->id, 'general.site_name', 'Имя из настроек', 'string');

        $merged = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);

        $this->assertSame('Имя из настроек', $merged['meta_brand_name']);
    }

    public function test_meta_brand_name_prefills_from_tenant_default_when_no_general_site_name(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_prefill_default');

        $merged = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);

        $this->assertSame('Tenant bn_prefill_default', $merged['meta_brand_name']);
    }

    public function test_meta_brand_name_not_overridden_when_saved_in_questionnaire(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_prefill_custom');
        TenantSetting::setForTenant($tenant->id, 'general.site_name', 'Из настроек', 'string');

        TenantSetting::setForTenant($tenant->id, BookingNotificationsQuestionnaireRepository::SETTING_KEY, [
            'schema_version' => 1,
            'meta_brand_name' => 'Своё из анкеты',
        ], 'json');

        $merged = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);

        $this->assertSame('Своё из анкеты', $merged['meta_brand_name']);
    }

    public function test_meta_brand_name_stays_empty_when_user_saved_explicit_empty(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_prefill_empty_brand');
        TenantSetting::setForTenant($tenant->id, 'general.site_name', 'Из настроек', 'string');

        TenantSetting::setForTenant($tenant->id, BookingNotificationsQuestionnaireRepository::SETTING_KEY, [
            'schema_version' => 1,
            'meta_brand_name' => '',
            'questionnaire_intent' => [
                'explicit_empty' => ['meta_brand_name'],
                'sched_customized' => false,
                'events_customized' => false,
            ],
        ], 'json');

        $merged = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);

        $this->assertSame('', $merged['meta_brand_name']);
    }

    public function test_meta_timezone_prefills_from_tenant_when_questionnaire_empty(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_prefill_tz', ['timezone' => 'Europe/Moscow']);

        $merged = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);

        $this->assertSame('Europe/Moscow', $merged['meta_timezone']);
    }

    public function test_dest_email_prefills_from_wizard_notification_destination(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_prefill_email');

        NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => BookingNotificationsBriefingWizardMarkers::DEST_EMAIL_NAME,
            'type' => NotificationChannelType::Email->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => ['email' => 'hello@example.test'],
        ]);

        $merged = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);

        $this->assertSame('hello@example.test', $merged['dest_email']);
    }

    public function test_dest_telegram_prefills_from_wizard_destination_first(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_prefill_tg');

        NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => BookingNotificationsBriefingWizardMarkers::DEST_TELEGRAM_NAME,
            'type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDestinationStatus::Draft->value,
            'is_shared' => true,
            'config_json' => ['chat_id' => '-1001234567890'],
        ]);

        $merged = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);

        $this->assertSame('-1001234567890', $merged['dest_telegram_chat_id']);
    }

    public function test_sched_fields_prefill_from_existing_booking_preset_when_questionnaire_still_defaults(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_prefill_sched');

        BookingSettingsPreset::query()->create([
            'tenant_id' => $tenant->id,
            'name' => BookingNotificationsBriefingWizardMarkers::PRESET_NAME,
            'description' => null,
            'payload' => [
                'duration_minutes' => 90,
                'slot_step_minutes' => 20,
                'buffer_before_minutes' => 5,
                'buffer_after_minutes' => 5,
                'min_booking_notice_minutes' => 60,
                'max_booking_horizon_days' => 30,
                'requires_confirmation' => false,
            ],
        ]);

        $merged = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);

        $this->assertSame(90, $merged['sched_duration_min']);
        $this->assertSame(20, $merged['sched_slot_step_min']);
        $this->assertSame(5, $merged['sched_buffer_before']);
        $this->assertSame(5, $merged['sched_buffer_after']);
        $this->assertSame(60, $merged['sched_notice_min']);
        $this->assertSame(30, $merged['sched_horizon_days']);
        $this->assertFalse($merged['sched_requires_confirmation']);
    }

    public function test_events_enabled_strips_booking_when_scheduling_disabled_and_list_still_default(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_prefill_events_sched_off', [
            'scheduling_module_enabled' => false,
        ]);

        $merged = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);

        $this->assertSame(['crm_request.created'], $merged['events_enabled']);
    }

    public function test_save_draft_does_not_flag_events_customized_when_scheduling_off_and_effective_default(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_intent_events_sched_off', [
            'scheduling_module_enabled' => false,
        ]);

        $merged = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);
        $this->assertSame(['crm_request.created'], $merged['events_enabled']);

        app(BookingNotificationsQuestionnaireRepository::class)->save($tenant->id, $merged);

        $raw = TenantSetting::getForTenant($tenant->id, BookingNotificationsQuestionnaireRepository::SETTING_KEY, []);
        $intent = is_array($raw) ? ($raw['questionnaire_intent'] ?? []) : [];
        $this->assertFalse((bool) ($intent['events_customized'] ?? false));
    }

    /**
     * Список в черновике совпадает с tenant-effective default (без booking.* при выключенном scheduling),
     * intent: не кастомизировал события. Подписки мастера дают дополнительный lead.* — getMerged() должен
     * брать ключи из подписок, а не отбрасывать их из‑за сравнения с глобальным default анкеты.
     */
    public function test_getMerged_prefers_wizard_event_keys_when_list_matches_tenant_effective_default(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_merge_wizard_effective', [
            'scheduling_module_enabled' => false,
        ]);

        TenantSetting::setForTenant($tenant->id, BookingNotificationsQuestionnaireRepository::SETTING_KEY, [
            'schema_version' => 1,
            'events_enabled' => ['crm_request.created'],
            'questionnaire_intent' => [
                'explicit_empty' => [],
                'sched_customized' => false,
                'events_customized' => false,
            ],
        ], 'json');

        $marker = BookingNotificationsBriefingWizardMarkers::SUBSCRIPTION_NAME_MARKER;
        foreach (['crm_request.created', 'lead.created'] as $eventKey) {
            NotificationSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => null,
                'name' => 'Подписка ('.$marker.') '.$eventKey,
                'event_key' => $eventKey,
                'enabled' => true,
                'conditions_json' => null,
                'schedule_json' => null,
                'severity_min' => null,
                'created_by_user_id' => null,
            ]);
        }

        $merged = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);

        $this->assertEqualsCanonicalizing(
            ['crm_request.created', 'lead.created'],
            $merged['events_enabled']
        );
    }

    public function test_events_enabled_prefills_from_wizard_subscriptions_when_still_default_list(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_prefill_events');

        NotificationSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'Новая заявка ('.BookingNotificationsBriefingWizardMarkers::SUBSCRIPTION_NAME_MARKER.')',
            'event_key' => 'crm_request.created',
            'enabled' => true,
            'conditions_json' => null,
            'schedule_json' => null,
            'severity_min' => null,
            'created_by_user_id' => null,
        ]);

        $merged = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);

        $this->assertSame(['crm_request.created'], $merged['events_enabled']);
    }

    public function test_saved_events_enabled_not_overridden_when_differs_from_defaults(): void
    {
        $tenant = $this->createTenantWithActiveDomain('bn_prefill_events_saved');

        TenantSetting::setForTenant($tenant->id, BookingNotificationsQuestionnaireRepository::SETTING_KEY, [
            'schema_version' => 1,
            'events_enabled' => ['crm_request.created'],
        ], 'json');

        NotificationSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'Другое ('.BookingNotificationsBriefingWizardMarkers::SUBSCRIPTION_NAME_MARKER.')',
            'event_key' => 'lead.created',
            'enabled' => true,
            'conditions_json' => null,
            'schedule_json' => null,
            'severity_min' => null,
            'created_by_user_id' => null,
        ]);

        $merged = app(BookingNotificationsQuestionnaireRepository::class)->getMerged($tenant->id);

        $this->assertSame(['crm_request.created'], $merged['events_enabled']);
    }
}
