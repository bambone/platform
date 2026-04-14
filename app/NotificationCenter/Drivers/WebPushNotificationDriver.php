<?php

namespace App\NotificationCenter\Drivers;

use App\Models\NotificationDelivery;
use App\Models\NotificationDestination;
use App\Models\NotificationEvent;
use App\Models\NotificationPushSubscription;
use App\NotificationCenter\ChannelSendResult;
use App\NotificationCenter\Contracts\NotificationChannelDriver;
use App\NotificationCenter\NotificationDeliveryStatus;
use App\NotificationCenter\UnsupportedNotificationChannelException;
use App\Services\Platform\PlatformNotificationSettings;
use Illuminate\Support\Carbon;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

final class WebPushNotificationDriver implements NotificationChannelDriver
{
    public function __construct(
        private readonly PlatformNotificationSettings $platform,
    ) {}

    public function send(
        NotificationDelivery $delivery,
        NotificationEvent $event,
        NotificationDestination $destination,
    ): ChannelSendResult {
        if (! class_exists(WebPush::class)) {
            throw new UnsupportedNotificationChannelException(
                'Web Push: add composer package minishlink/web-push (see docs).'
            );
        }

        $public = $this->platform->vapidPublicKey();
        $private = $this->platform->vapidPrivateKeyDecrypted();
        if ($public === null || $private === null) {
            throw new \RuntimeException('VAPID keys are not configured on platform.');
        }

        $subject = (string) config('notification_center.webpush.vapid_subject', 'mailto:noreply@example.com');

        $userId = $destination->user_id;
        if ($userId === null) {
            throw new \InvalidArgumentException('Web push destination must be bound to a user.');
        }

        $subs = NotificationPushSubscription::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where(function ($q) use ($destination): void {
                $q->whereNull('destination_id')->orWhere('destination_id', $destination->id);
            })
            ->get();

        if ($subs->isEmpty()) {
            throw new \RuntimeException('No active push subscriptions for this user.');
        }

        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $public,
                'privateKey' => $private,
            ],
        ];

        /** @var class-string<WebPush> $webPushClass */
        $webPushClass = WebPush::class;
        $webPush = new $webPushClass($auth);

        $payload = $event->payloadDto();
        $json = json_encode([
            'title' => $payload->title,
            'body' => $payload->body,
            'url' => $payload->actionUrl,
            'notification_event_id' => (int) $event->id,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $subscriptionClass = Subscription::class;
        foreach ($subs as $sub) {
            $subscription = $subscriptionClass::create([
                'endpoint' => $sub->endpoint,
                'keys' => [
                    'p256dh' => $sub->public_key,
                    'auth' => $sub->auth_token,
                ],
            ]);
            $webPush->queueNotification($subscription, $json);
        }

        try {
            $anySuccess = false;
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $anySuccess = true;
                }
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException('Web push request failed: '.$e->getMessage(), previous: $e);
        }

        if (! $anySuccess) {
            throw new \RuntimeException('All web push deliveries failed.');
        }

        $now = Carbon::now();

        return new ChannelSendResult(
            status: NotificationDeliveryStatus::Sent,
            sentAt: $now,
            deliveredAt: null,
            responseJson: ['subscriptions' => $subs->count()],
        );
    }
}
