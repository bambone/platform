<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Models\BookingSettingsPreset;
use App\Models\NotificationDestination;
use App\Models\NotificationSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\NotificationCenter\NotificationChannelType;
use App\NotificationCenter\NotificationDestinationStatus;
use App\NotificationCenter\NotificationEventRegistry;
use App\Scheduling\BookableServiceSettingsMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Применяет ответы анкеты: пресет записи, получатели, правила уведомлений.
 */
final class BookingNotificationsBriefingApplier
{
    public function __construct(
        private readonly BookingNotificationsQuestionnaireRepository $questionnaire,
        private readonly BookableServiceSettingsMapper $mapper,
        private readonly SetupProfileRepository $setupProfile,
        private readonly TenantOnboardingBranchResolver $branchResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data  merged questionnaire state
     * @return array{destinations_created: int, subscriptions_created: int, preset_id: ?int}
     */
    public function apply(Tenant $tenant, User $user, array $data): array
    {
        $this->questionnaire->save($tenant->id, $data);

        $destCount = 0;
        $subCount = 0;
        $presetId = null;

        $destinationIds = [];

        DB::transaction(function () use ($tenant, $user, $data, &$destCount, &$subCount, &$presetId, &$destinationIds): void {
            $branchResolution = $this->branchResolver->resolve(
                $tenant,
                $user,
                $this->setupProfile->getMerged((int) $tenant->id),
            );

            if (! $branchResolution->shouldSuppressBookingAutomation()
                && Gate::forUser($user)->allows('manage_scheduling')
                && $tenant->scheduling_module_enabled) {
                $presetId = $this->upsertPreset($tenant, $data);
            }

            if (Gate::forUser($user)->allows('manage_notification_destinations') || Gate::forUser($user)->allows('manage_notifications')) {
                $email = trim((string) ($data['dest_email'] ?? ''));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $destinationIds[] = $this->upsertEmailDestination($tenant, $user, $email);
                    $destCount++;
                }

                $tg = trim((string) ($data['dest_telegram_chat_id'] ?? ''));
                if ($tg !== '') {
                    $destinationIds[] = $this->upsertTelegramDestination($tenant, $user, $tg);
                    $destCount++;
                }
            }

            $destinationIds = array_values(array_unique(array_filter($destinationIds)));

            if ($destinationIds !== []
                && (Gate::forUser($user)->allows('manage_notification_subscriptions') || Gate::forUser($user)->allows('manage_notifications'))) {
                $events = $data['events_enabled'] ?? [];
                if (! is_array($events)) {
                    $events = [];
                }
                $events = array_values(array_filter(array_map('strval', $events)));
                if ($branchResolution->shouldFilterBookingNotificationEvents()) {
                    $events = array_values(array_filter(
                        $events,
                        static fn (string $key): bool => ! str_starts_with($key, 'booking.'),
                    ));
                }
                foreach ($events as $eventKey) {
                    if (! NotificationEventRegistry::has($eventKey)) {
                        continue;
                    }
                    $this->upsertSubscription($tenant, $user, $eventKey, $destinationIds);
                    $subCount++;
                }
            }

            $this->questionnaire->markApplied($tenant->id);
        });

        SetupProgressCache::forget((int) $tenant->id);

        return [
            'destinations_created' => $destCount,
            'subscriptions_created' => $subCount,
            'preset_id' => $presetId,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertPreset(Tenant $tenant, array $data): int
    {
        $raw = [
            'duration_minutes' => (int) ($data['sched_duration_min'] ?? 60),
            'slot_step_minutes' => (int) ($data['sched_slot_step_min'] ?? 15),
            'buffer_before_minutes' => (int) ($data['sched_buffer_before'] ?? 0),
            'buffer_after_minutes' => (int) ($data['sched_buffer_after'] ?? 0),
            'min_booking_notice_minutes' => (int) ($data['sched_notice_min'] ?? 120),
            'max_booking_horizon_days' => (int) ($data['sched_horizon_days'] ?? 60),
            'requires_confirmation' => (bool) ($data['sched_requires_confirmation'] ?? true),
            'sort_weight' => 0,
            'sync_title_from_source' => true,
        ];
        $payload = $this->mapper->extractWhitelisted($raw);

        $preset = BookingSettingsPreset::query()->firstOrNew([
            'tenant_id' => $tenant->id,
            'name' => BookingNotificationsBriefingWizardMarkers::PRESET_NAME,
        ]);
        $preset->description = 'Создано мастером запуска (анкета).';
        $preset->payload = $payload;
        $preset->save();

        return (int) $preset->id;
    }

    private function upsertEmailDestination(Tenant $tenant, User $user, string $email): int
    {
        $shared = Gate::forUser($user)->allows('manage_notifications');

        $dest = NotificationDestination::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', NotificationChannelType::Email->value)
            ->where('name', BookingNotificationsBriefingWizardMarkers::DEST_EMAIL_NAME)
            ->first();

        if ($dest === null) {
            $dest = new NotificationDestination([
                'tenant_id' => $tenant->id,
                'name' => BookingNotificationsBriefingWizardMarkers::DEST_EMAIL_NAME,
                'type' => NotificationChannelType::Email->value,
            ]);
        }

        $dest->status = NotificationDestinationStatus::Draft->value;
        $dest->is_shared = $shared;
        $dest->user_id = $shared ? null : $user->id;
        $dest->config_json = array_merge($dest->config_json ?? [], ['email' => $email]);
        $dest->save();

        return (int) $dest->id;
    }

    private function upsertTelegramDestination(Tenant $tenant, User $user, string $chatId): int
    {
        $shared = Gate::forUser($user)->allows('manage_notifications');

        $dest = NotificationDestination::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', NotificationChannelType::Telegram->value)
            ->where('name', BookingNotificationsBriefingWizardMarkers::DEST_TELEGRAM_NAME)
            ->first();

        if ($dest === null) {
            $dest = new NotificationDestination([
                'tenant_id' => $tenant->id,
                'name' => BookingNotificationsBriefingWizardMarkers::DEST_TELEGRAM_NAME,
                'type' => NotificationChannelType::Telegram->value,
            ]);
        }

        $dest->status = NotificationDestinationStatus::Draft->value;
        $dest->is_shared = $shared;
        $dest->user_id = $shared ? null : $user->id;
        $dest->config_json = array_merge($dest->config_json ?? [], ['chat_id' => $chatId]);
        $dest->save();

        return (int) $dest->id;
    }

    /**
     * @param  list<int>  $destinationIds
     */
    private function upsertSubscription(Tenant $tenant, User $user, string $eventKey, array $destinationIds): void
    {
        $def = NotificationEventRegistry::definition($eventKey);
        if ($def === null) {
            return;
        }

        $sharedRules = Gate::forUser($user)->allows('manage_notifications');

        $query = NotificationSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_key', $eventKey);
        if ($sharedRules) {
            $query->whereNull('user_id');
        } else {
            $query->where('user_id', $user->id);
        }
        $existing = $query->first();

        if ($existing !== null) {
            $sub = $existing;
        } else {
            $sub = new NotificationSubscription([
                'tenant_id' => $tenant->id,
                'user_id' => $sharedRules ? null : $user->id,
                'event_key' => $eventKey,
                'created_by_user_id' => $user->id,
            ]);
        }

        $sub->name = $def->defaultTitle.' ('.BookingNotificationsBriefingWizardMarkers::SUBSCRIPTION_NAME_MARKER.')';
        $sub->enabled = true;
        $sub->severity_min = $def->defaultSeverity->value;
        $sub->conditions_json = null;
        $sub->schedule_json = null;
        $sub->save();

        $sync = [];
        $order = 0;
        foreach ($destinationIds as $id) {
            $sync[(int) $id] = [
                'delivery_mode' => 'immediate',
                'delay_seconds' => null,
                'order_index' => $order++,
                'is_enabled' => true,
            ];
        }
        $sub->destinations()->sync($sync);
    }

    /**
     * Проверка перед применением (вызывать из UI).
     */
    public function assertCanApplySomething(User $user): void
    {
        $g = Gate::forUser($user);
        if ($g->allows('manage_scheduling')
            || $g->allows('manage_notifications')
            || $g->allows('manage_notification_destinations')
            || $g->allows('manage_notification_subscriptions')) {
            return;
        }

        throw ValidationException::withMessages([
            'apply' => 'Недостаточно прав для применения настроек (нужны права на запись и/или уведомления).',
        ]);
    }
}
