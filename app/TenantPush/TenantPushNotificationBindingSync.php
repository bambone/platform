<?php

declare(strict_types=1);

namespace App\TenantPush;

use App\Models\NotificationDestination;
use App\Models\NotificationSubscription;
use App\Models\Tenant;
use App\NotificationCenter\NotificationChannelType;
use App\NotificationCenter\NotificationDestinationStatus;

final class TenantPushNotificationBindingSync
{
    public function __construct(
        private readonly TenantPushCrmRequestRecipientResolver $recipientResolver,
    ) {}

    /**
     * Sync personal subscriptions + OneSignal destinations for crm_request.created.
     */
    public function syncCrmRequestCreated(Tenant $tenant): void
    {
        $userIds = $this->recipientResolver->resolveOnesignalRecipientUserIds($tenant);
        $subscriptionName = 'OneSignal: новая заявка';

        $existing = NotificationSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_key', 'crm_request.created')
            ->where('name', $subscriptionName)
            ->get();

        foreach ($existing as $sub) {
            if (! in_array((int) $sub->user_id, $userIds, true)) {
                $sub->destinations()->detach();
                $sub->delete();
            }
        }

        if ($userIds === []) {
            return;
        }

        foreach ($userIds as $userId) {
            $destination = NotificationDestination::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $userId,
                    'type' => NotificationChannelType::WebPushOnesignal->value,
                ],
                [
                    'name' => 'OneSignal Web Push',
                    'status' => NotificationDestinationStatus::PendingVerification->value,
                    'is_shared' => false,
                    'config_json' => [],
                ],
            );

            $identity = \App\Models\TenantOnesignalPushIdentity::query()
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->exists();

            $destination->update([
                'status' => $identity
                    ? NotificationDestinationStatus::Verified->value
                    : NotificationDestinationStatus::PendingVerification->value,
            ]);

            $subscription = NotificationSubscription::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $userId,
                    'event_key' => 'crm_request.created',
                    'name' => $subscriptionName,
                ],
                [
                    'enabled' => true,
                    'conditions_json' => null,
                    'schedule_json' => null,
                    'severity_min' => null,
                    'created_by_user_id' => null,
                ],
            );

            if (! $subscription->destinations()->where('notification_destinations.id', $destination->id)->exists()) {
                $subscription->destinations()->attach($destination->id, [
                    'delivery_mode' => 'immediate',
                    'delay_seconds' => null,
                    'order_index' => 10,
                    'is_enabled' => true,
                ]);
            }
        }
    }
}
