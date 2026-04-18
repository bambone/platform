<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Models\BookingSettingsPreset;
use App\Models\NotificationDestination;
use App\Models\NotificationSubscription;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\NotificationCenter\NotificationChannelType;
use App\NotificationCenter\NotificationEventRegistry;
use App\Scheduling\SchedulingTimezoneOptions;

/**
 * Черновик и результат анкеты «Запись и уведомления» (v1) для мастера запуска.
 *
 * @phpstan-type QuestionnaireData array{
 *   schema_version?: int,
 *   questionnaire_intent?: array{explicit_empty?: list<string>, sched_customized?: bool, events_customized?: bool},
 *   meta_brand_name?: string,
 *   meta_timezone?: string,
 *   sched_duration_min?: int|null,
 *   sched_slot_step_min?: int|null,
 *   sched_buffer_before?: int|null,
 *   sched_buffer_after?: int|null,
 *   sched_horizon_days?: int|null,
 *   sched_notice_min?: int|null,
 *   sched_requires_confirmation?: bool|null,
 *   dest_email?: string,
 *   dest_telegram_chat_id?: string,
 *   events_enabled?: list<string>,
 *   applied_at?: string|null,
 * }
 */
final class BookingNotificationsQuestionnaireRepository
{
    public const SETTING_KEY = 'setup.booking_notifications_questionnaire';

    /** Отметка времени успешного apply; читается чеклистом запуска и не дублирует логику snapshot. */
    public const APPLIED_AT_KEY = 'setup.booking_notifications_applied_at';

    public function schemaVersion(): int
    {
        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'schema_version' => $this->schemaVersion(),
            'meta_brand_name' => '',
            'meta_timezone' => '',
            'sched_duration_min' => 60,
            'sched_slot_step_min' => 15,
            'sched_buffer_before' => 0,
            'sched_buffer_after' => 0,
            'sched_horizon_days' => 60,
            'sched_notice_min' => 120,
            'sched_requires_confirmation' => true,
            'dest_email' => '',
            'dest_telegram_chat_id' => '',
            'events_enabled' => [
                'crm_request.created',
                'booking.created',
            ],
        ];
    }

    /**
     * Пустые поля черновика дополняются из БД только если пользователь не пометил поле как «явно пустое»
     * ({@see mergeIntentOnSave()} / {@see questionnaire_intent.explicit_empty}).
     * Пресет записи подставляется только с именем мастера ({@see BookingNotificationsBriefingWizardMarkers::PRESET_NAME}),
     * без «первого пресета по id». Email/Telegram — только из получателей с маркером мастера.
     * Часовой пояс: канонизация через {@see SchedulingTimezoneOptions::tryResolveToKnownIdentifier()}, без тихой подмены мусора на Москву.
     *
     * @return array<string, mixed>
     */
    public function getMerged(int $tenantId): array
    {
        $raw = TenantSetting::getForTenant($tenantId, self::SETTING_KEY, []);
        $raw = is_array($raw) ? $raw : [];

        $intent = $this->normalizeIntent($raw['questionnaire_intent'] ?? []);
        unset($raw['questionnaire_intent']);

        $merged = array_merge($this->defaults(), $raw);

        $tenant = Tenant::query()->find($tenantId);

        if ($this->shouldPrefillMetaBrandNameFromDatabase($merged, $intent)) {
            $fallback = $this->resolvedPublicSiteName($tenantId);
            if ($fallback !== '') {
                $merged['meta_brand_name'] = $fallback;
            }
        }

        if ($this->shouldPrefillMetaTimezoneFromDatabase($merged, $intent)) {
            $tz = $this->resolvedTimezone($tenant);
            if ($tz !== '') {
                $merged['meta_timezone'] = $tz;
            }
        }

        if ($this->shouldPrefillDestEmailFromDatabase($merged, $intent)) {
            $email = $this->resolvedEmailFromDestinations($tenantId);
            if ($email !== '') {
                $merged['dest_email'] = $email;
            }
        }

        if ($this->shouldPrefillDestTelegramFromDatabase($merged, $intent)) {
            $tg = $this->resolvedTelegramFromDestinations($tenantId);
            if ($tg !== '') {
                $merged['dest_telegram_chat_id'] = $tg;
            }
        }

        if ($this->schedFieldsMatchDefaults($merged) && ! ($intent['sched_customized'] ?? false)) {
            $sched = $this->schedFieldsFromBookingPreset($tenantId);
            if ($sched !== null) {
                $merged = array_merge($merged, $sched);
            }
        }

        if ($tenant !== null) {
            $wizardKeys = $this->eventKeysFromWizardSubscriptions($tenantId);
            $stillDefaultEvents = $this->eventsListMatchesTenantEffectiveDefaults($merged, $tenant);
            $eventsCustomized = (bool) ($intent['events_customized'] ?? false);
            if ($wizardKeys !== [] && $stillDefaultEvents && ! $eventsCustomized) {
                $source = $wizardKeys;
            } else {
                $source = array_values(array_map('strval', (array) ($merged['events_enabled'] ?? [])));
            }
            $merged['events_enabled'] = $this->filterEventKeysForTenant($tenant, $source);
        }

        $tzRaw = trim((string) ($merged['meta_timezone'] ?? ''));
        if ($tzRaw !== '') {
            $known = SchedulingTimezoneOptions::tryResolveToKnownIdentifier($tzRaw);
            if ($known !== null) {
                $merged['meta_timezone'] = $known;
            }
        }

        unset($merged['questionnaire_intent']);

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{explicit_empty: list<string>, sched_customized: bool, events_customized: bool}
     */
    private function normalizeIntent(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [
                'explicit_empty' => [],
                'sched_customized' => false,
                'events_customized' => false,
            ];
        }

        $empty = $raw['explicit_empty'] ?? [];
        if (! is_array($empty)) {
            $empty = [];
        }

        $empty = array_values(array_filter(array_map('strval', $empty)));

        return [
            'explicit_empty' => $empty,
            'sched_customized' => (bool) ($raw['sched_customized'] ?? false),
            'events_customized' => (bool) ($raw['events_customized'] ?? false),
        ];
    }

    /**
     * Пока в черновике анкеты нет своего значения — подставляем публичное имя сайта из настроек / тенанта.
     *
     * @param  array<string, mixed>  $merged
     * @param  array{explicit_empty: list<string>, sched_customized: bool, events_customized: bool}  $intent
     */
    private function shouldPrefillMetaBrandNameFromDatabase(array $merged, array $intent): bool
    {
        if (trim((string) ($merged['meta_brand_name'] ?? '')) !== '') {
            return false;
        }

        return ! $this->fieldExplicitlyEmpty($intent, 'meta_brand_name');
    }

    private function resolvedPublicSiteName(int $tenantId): string
    {
        $fromSettings = trim((string) TenantSetting::getForTenant($tenantId, 'general.site_name', ''));
        if ($fromSettings !== '') {
            return $fromSettings;
        }

        $tenant = Tenant::query()->find($tenantId);

        return $tenant !== null ? trim($tenant->defaultPublicSiteName()) : '';
    }

    /**
     * @param  array<string, mixed>  $merged
     * @param  array{explicit_empty: list<string>, sched_customized: bool, events_customized: bool}  $intent
     */
    private function shouldPrefillMetaTimezoneFromDatabase(array $merged, array $intent): bool
    {
        if (trim((string) ($merged['meta_timezone'] ?? '')) !== '') {
            return false;
        }

        return ! $this->fieldExplicitlyEmpty($intent, 'meta_timezone');
    }

    private function resolvedTimezone(?Tenant $tenant): string
    {
        if ($tenant !== null) {
            $tz = trim((string) ($tenant->timezone ?? ''));
            if ($tz !== '') {
                $known = SchedulingTimezoneOptions::tryResolveToKnownIdentifier($tz);

                return $known ?? $tz;
            }
        }

        $cfg = trim((string) config('app.timezone', 'UTC'));
        if ($cfg !== '') {
            $known = SchedulingTimezoneOptions::tryResolveToKnownIdentifier($cfg);
            if ($known !== null) {
                return $known;
            }

            return $cfg;
        }

        return SchedulingTimezoneOptions::defaultForNewForm();
    }

    /**
     * @param  array<string, mixed>  $merged
     * @param  array{explicit_empty: list<string>, sched_customized: bool, events_customized: bool}  $intent
     */
    private function shouldPrefillDestEmailFromDatabase(array $merged, array $intent): bool
    {
        if (trim((string) ($merged['dest_email'] ?? '')) !== '') {
            return false;
        }

        return ! $this->fieldExplicitlyEmpty($intent, 'dest_email');
    }

    /**
     * @param  array<string, mixed>  $merged
     * @param  array{explicit_empty: list<string>, sched_customized: bool, events_customized: bool}  $intent
     */
    private function shouldPrefillDestTelegramFromDatabase(array $merged, array $intent): bool
    {
        if (trim((string) ($merged['dest_telegram_chat_id'] ?? '')) !== '') {
            return false;
        }

        return ! $this->fieldExplicitlyEmpty($intent, 'dest_telegram_chat_id');
    }

    /**
     * @param  array{explicit_empty: list<string>, sched_customized: bool, events_customized: bool}  $intent
     */
    private function fieldExplicitlyEmpty(array $intent, string $fieldKey): bool
    {
        return in_array($fieldKey, $intent['explicit_empty'], true);
    }

    private function resolvedEmailFromDestinations(int $tenantId): string
    {
        $wizard = NotificationDestination::query()
            ->where('tenant_id', $tenantId)
            ->where('type', NotificationChannelType::Email->value)
            ->where('name', BookingNotificationsBriefingWizardMarkers::DEST_EMAIL_NAME)
            ->first();
        if ($wizard !== null) {
            $email = trim((string) ($wizard->config_json['email'] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return '';
    }

    private function resolvedTelegramFromDestinations(int $tenantId): string
    {
        $wizard = NotificationDestination::query()
            ->where('tenant_id', $tenantId)
            ->where('type', NotificationChannelType::Telegram->value)
            ->where('name', BookingNotificationsBriefingWizardMarkers::DEST_TELEGRAM_NAME)
            ->first();
        if ($wizard !== null) {
            $chatId = trim((string) ($wizard->config_json['chat_id'] ?? ''));
            if ($chatId !== '') {
                return $chatId;
            }
        }

        return '';
    }

    /**
     * Анкета ещё на «заводских» числах пресета — можно подтянуть живой пресет из БД.
     *
     * @param  array<string, mixed>  $merged
     */
    private function schedFieldsMatchDefaults(array $merged): bool
    {
        $d = $this->defaults();

        return (int) ($merged['sched_duration_min'] ?? 0) === (int) $d['sched_duration_min']
            && (int) ($merged['sched_slot_step_min'] ?? 0) === (int) $d['sched_slot_step_min']
            && (int) ($merged['sched_buffer_before'] ?? 0) === (int) $d['sched_buffer_before']
            && (int) ($merged['sched_buffer_after'] ?? 0) === (int) $d['sched_buffer_after']
            && (int) ($merged['sched_horizon_days'] ?? 0) === (int) $d['sched_horizon_days']
            && (int) ($merged['sched_notice_min'] ?? 0) === (int) $d['sched_notice_min']
            && (bool) ($merged['sched_requires_confirmation'] ?? false) === (bool) $d['sched_requires_confirmation'];
    }

    /**
     * Только пресет с маркером мастера (без «первого попавшегося» пресета).
     *
     * @return array<string, mixed>|null
     */
    private function schedFieldsFromBookingPreset(int $tenantId): ?array
    {
        $preset = BookingSettingsPreset::query()
            ->where('tenant_id', $tenantId)
            ->where('name', BookingNotificationsBriefingWizardMarkers::PRESET_NAME)
            ->first();

        if ($preset === null) {
            return null;
        }

        $payload = is_array($preset->payload) ? $preset->payload : [];
        $d = $this->defaults();

        return [
            'sched_duration_min' => (int) ($payload['duration_minutes'] ?? $d['sched_duration_min']),
            'sched_slot_step_min' => (int) ($payload['slot_step_minutes'] ?? $d['sched_slot_step_min']),
            'sched_buffer_before' => (int) ($payload['buffer_before_minutes'] ?? $d['sched_buffer_before']),
            'sched_buffer_after' => (int) ($payload['buffer_after_minutes'] ?? $d['sched_buffer_after']),
            'sched_notice_min' => (int) ($payload['min_booking_notice_minutes'] ?? $d['sched_notice_min']),
            'sched_horizon_days' => (int) ($payload['max_booking_horizon_days'] ?? $d['sched_horizon_days']),
            'sched_requires_confirmation' => (bool) ($payload['requires_confirmation'] ?? $d['sched_requires_confirmation']),
        ];
    }

    /**
     * Сравнение с дефолтом с учётом модуля записи (как в {@see getMerged()} / {@see filterEventKeysForTenant()}).
     *
     * @param  array<string, mixed>  $merged
     */
    private function eventsListMatchesTenantEffectiveDefaults(array $merged, ?Tenant $tenant): bool
    {
        $base = $this->defaults()['events_enabled'];
        $effective = $tenant !== null
            ? $this->filterEventKeysForTenant($tenant, $base)
            : $base;

        $cur = $merged['events_enabled'] ?? [];
        if (! is_array($cur)) {
            return false;
        }

        $a = array_map('strval', $effective);
        $b = array_map('strval', $cur);
        sort($a);
        sort($b);

        return $a === $b;
    }

    /**
     * @return list<string>
     */
    private function eventKeysFromWizardSubscriptions(int $tenantId): array
    {
        $marker = BookingNotificationsBriefingWizardMarkers::SUBSCRIPTION_NAME_MARKER;

        $keys = NotificationSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('name', 'like', '%'.$marker.'%')
            ->pluck('event_key')
            ->map(fn ($k): string => (string) $k)
            ->filter()
            ->unique()
            ->values()
            ->all();

        sort($keys);

        return array_values(array_filter($keys, fn (string $k): bool => NotificationEventRegistry::has($k)));
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function filterEventKeysForTenant(Tenant $tenant, array $keys): array
    {
        $schedulingOn = (bool) $tenant->scheduling_module_enabled;

        $out = [];
        foreach ($keys as $key) {
            if (! NotificationEventRegistry::has($key)) {
                continue;
            }
            if (! $schedulingOn && str_starts_with($key, 'booking.')) {
                continue;
            }
            $out[] = $key;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(int $tenantId, array $data): void
    {
        $previous = TenantSetting::getForTenant($tenantId, self::SETTING_KEY, []);
        $previous = is_array($previous) ? $previous : [];

        $data['questionnaire_intent'] = $this->mergeIntentOnSave($tenantId, $previous, $data);
        $data['schema_version'] = $this->schemaVersion();
        TenantSetting::setForTenant($tenantId, self::SETTING_KEY, $data, 'json');
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $data
     * @return array{explicit_empty: list<string>, sched_customized: bool, events_customized: bool}
     */
    private function mergeIntentOnSave(int $tenantId, array $previous, array $data): array
    {
        $prior = $this->normalizeIntent($previous['questionnaire_intent'] ?? []);

        $explicitKeys = ['meta_brand_name', 'meta_timezone', 'dest_email', 'dest_telegram_chat_id'];
        $explicitEmpty = [];
        foreach ($explicitKeys as $key) {
            if (trim((string) ($data[$key] ?? '')) === '') {
                $explicitEmpty[] = $key;
            }
        }
        $explicitEmpty = array_values(array_unique($explicitEmpty));

        $d = $this->defaults();
        $mergedForCompare = array_merge($d, $data);
        $schedKeys = [
            'sched_duration_min',
            'sched_slot_step_min',
            'sched_buffer_before',
            'sched_buffer_after',
            'sched_horizon_days',
            'sched_notice_min',
            'sched_requires_confirmation',
        ];
        $schedDiffers = false;
        foreach ($schedKeys as $sk) {
            $newVal = $mergedForCompare[$sk] ?? $d[$sk];
            $defVal = $d[$sk];
            if ($sk === 'sched_requires_confirmation') {
                if ((bool) $newVal !== (bool) $defVal) {
                    $schedDiffers = true;
                    break;
                }
            } elseif ((int) $newVal !== (int) $defVal) {
                $schedDiffers = true;
                break;
            }
        }
        $schedCustomized = $prior['sched_customized'] || $schedDiffers;

        $tenant = Tenant::query()->find($tenantId);
        $eventsCustomized = $prior['events_customized']
            || ! $this->eventsListMatchesTenantEffectiveDefaults($mergedForCompare, $tenant);

        return [
            'explicit_empty' => $explicitEmpty,
            'sched_customized' => $schedCustomized,
            'events_customized' => $eventsCustomized,
        ];
    }

    public function appliedAt(int $tenantId): ?string
    {
        $v = TenantSetting::getForTenant($tenantId, self::APPLIED_AT_KEY, '');

        return is_string($v) && $v !== '' ? $v : null;
    }

    public function markApplied(int $tenantId): void
    {
        TenantSetting::setForTenant($tenantId, self::APPLIED_AT_KEY, now()->toIso8601String(), 'string');
    }
}
