<?php

namespace App\NotificationCenter;

use App\Jobs\DispatchNotificationDeliveryJob;
use App\Models\NotificationDelivery;
use App\Models\NotificationDestination;
use App\Models\NotificationEvent;
use App\Models\NotificationSubscription;
use App\Models\TenantPushSettings;
use App\TenantPush\TenantPushProviderStatus;
use Illuminate\Support\Carbon;

final class NotificationDeliveryPlanner
{
    public function __construct(
        private readonly NotificationSchedulePolicy $schedulePolicy,
    ) {}

    /**
     * Create queued deliveries for matching subscription rows (immediate mode only in core v1).
     *
     * @param  list<int>  $blockedDestinationIds  Destination ids already allocated for this event in this route pass (avoid duplicate deliveries).
     * @return list<NotificationDelivery>
     */
    public function planFromSubscription(
        NotificationEvent $event,
        NotificationSubscription $subscription,
        array $blockedDestinationIds = [],
    ): array {
        if ((int) $subscription->tenant_id !== (int) $event->tenant_id) {
            return [];
        }

        $created = [];
        $scheduleAllows = $this->schedulePolicy->allowsImmediateDelivery($subscription, $event);

        /** @var array<int, bool> Memoized only for this invocation (safe under Octane / persistent queue workers). */
        $tenantOnesignalProviderVerified = [];

        foreach ($subscription->destinations as $destination) {
            if ((int) $destination->tenant_id !== (int) $event->tenant_id) {
                continue;
            }

            if (in_array((int) $destination->id, $blockedDestinationIds, true)) {
                continue;
            }

            if (! $destination->pivot->is_enabled) {
                continue;
            }

            $mode = NotificationDeliveryMode::tryFrom((string) $destination->pivot->delivery_mode)
                ?? NotificationDeliveryMode::Immediate;

            if ($mode !== NotificationDeliveryMode::Immediate) {
                continue;
            }

            if (! $scheduleAllows) {
                continue;
            }

            if (! $this->destinationEligible($destination, $tenantOnesignalProviderVerified)) {
                continue;
            }

            $channelType = NotificationChannelType::tryFrom($destination->type);
            if ($channelType === null) {
                continue;
            }

            $delivery = NotificationDelivery::query()->create([
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->id,
                'subscription_id' => $subscription->id,
                'destination_id' => $destination->id,
                'channel_type' => $channelType->value,
                'status' => NotificationDeliveryStatus::Queued->value,
                'queued_at' => Carbon::now(),
            ]);

            $created[] = $delivery;

            DispatchNotificationDeliveryJob::dispatch($delivery->id)->afterCommit();
        }

        return $created;
    }

    /**
     * @param  array<int, bool>  $tenantOnesignalProviderVerified
     */
    private function destinationEligible(NotificationDestination $destination, array &$tenantOnesignalProviderVerified): bool
    {
        if ($destination->disabled_at !== null) {
            return false;
        }

        if ($destination->type === NotificationChannelType::InApp->value) {
            return in_array($destination->status, [
                NotificationDestinationStatus::Verified->value,
                NotificationDestinationStatus::Draft->value,
            ], true);
        }

        // OneSignal: queue only when tenant keys are verified and the destination row is Verified
        // (not PendingVerification — avoids noisy delivery attempts before subscription is ready).
        if ($destination->type === NotificationChannelType::WebPushOnesignal->value) {
            if (! $this->isTenantOnesignalProviderVerified((int) $destination->tenant_id, $tenantOnesignalProviderVerified)) {
                return false;
            }

            return $destination->status === NotificationDestinationStatus::Verified->value;
        }

        return $destination->status === NotificationDestinationStatus::Verified->value;
    }

    /**
     * @param  array<int, bool>  $cache
     */
    private function isTenantOnesignalProviderVerified(int $tenantId, array &$cache): bool
    {
        if (! array_key_exists($tenantId, $cache)) {
            $settings = TenantPushSettings::query()->where('tenant_id', $tenantId)->first();
            $cache[$tenantId] = $settings !== null
                && $settings->providerStatusEnum() === TenantPushProviderStatus::Verified;
        }

        return $cache[$tenantId];
    }
}
