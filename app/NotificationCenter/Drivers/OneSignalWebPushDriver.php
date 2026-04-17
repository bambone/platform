<?php

namespace App\NotificationCenter\Drivers;

use App\Models\NotificationDelivery;
use App\Models\NotificationDestination;
use App\Models\NotificationEvent;
use App\Models\TenantOnesignalPushIdentity;
use App\Models\TenantPushSettings;
use App\NotificationCenter\ChannelSendResult;
use App\NotificationCenter\Contracts\NotificationChannelDriver;
use App\NotificationCenter\NotificationDeliveryStatus;
use App\NotificationCenter\UnsupportedNotificationChannelException;
use App\Services\Platform\PlatformNotificationSettings;
use App\TenantPush\OneSignalExternalUserId;
use App\TenantPush\TenantPushFeatureGate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OneSignalWebPushDriver implements NotificationChannelDriver
{
    private const API_URL = 'https://api.onesignal.com/notifications';

    public function __construct(
        private readonly TenantPushFeatureGate $featureGate,
        private readonly PlatformNotificationSettings $platform,
    ) {}

    public function send(
        NotificationDelivery $delivery,
        NotificationEvent $event,
        NotificationDestination $destination,
    ): ChannelSendResult {
        if (! $this->platform->isChannelEnabled('web_push_onesignal')) {
            throw new UnsupportedNotificationChannelException('OneSignal Web Push disabled by platform.');
        }

        $tenant = $event->tenant;
        if ($tenant === null) {
            throw new \RuntimeException('Missing tenant on notification event.');
        }

        $gate = $this->featureGate->evaluate($tenant);
        if (! $gate->isFeatureEntitled()) {
            throw new UnsupportedNotificationChannelException('OneSignal Web Push not available for this tenant.');
        }

        $pushSettings = TenantPushSettings::query()->where('tenant_id', $tenant->id)->first();
        if ($pushSettings === null || ! $pushSettings->is_push_enabled) {
            return $this->skipped('no_push_settings', 'Push is not enabled for this tenant.');
        }

        $appId = trim((string) $pushSettings->onesignal_app_id);
        $apiKey = $pushSettings->onesignal_app_api_key_encrypted;
        if ($appId === '' || $apiKey === null || $apiKey === '') {
            return $this->skipped('not_configured', 'OneSignal app credentials are missing.');
        }

        $userId = $destination->user_id;
        if ($userId === null) {
            throw new \InvalidArgumentException('OneSignal web push destination must be bound to a user.');
        }

        $externalId = OneSignalExternalUserId::format((int) $tenant->id, (int) $userId);

        $identity = TenantOnesignalPushIdentity::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        if ($identity === null) {
            return $this->skipped('no_active_subscriptions', 'No active OneSignal subscription for this user.');
        }

        $payload = $event->payloadDto();
        $title = $payload->title !== '' ? $payload->title : 'Notification';
        $body = $payload->body !== '' ? $payload->body : '';
        $url = $payload->actionUrl;

        $requestBody = [
            'app_id' => $appId,
            'include_external_user_ids' => [$externalId],
            'headings' => ['en' => $title],
            'contents' => ['en' => $body],
        ];
        if ($url !== null && $url !== '') {
            $requestBody['url'] = $url;
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Authorization' => 'Key '.$apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->acceptJson()
                ->post(self::API_URL, $requestBody);
        } catch (\Throwable $e) {
            Log::warning('OneSignal HTTP request failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('OneSignal request failed: '.$e->getMessage(), previous: $e);
        }

        $json = $response->json();
        if (! $response->successful()) {
            $msg = is_array($json) ? ($json['errors'] ?? $json['error'] ?? $response->body()) : $response->body();
            if (is_array($msg)) {
                $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
            }

            return $this->skipped('onesignal_api_error', (string) $msg);
        }

        $id = is_array($json) ? ($json['id'] ?? null) : null;

        return new ChannelSendResult(
            status: NotificationDeliveryStatus::Sent,
            sentAt: Carbon::now(),
            deliveredAt: null,
            providerMessageId: is_string($id) ? $id : null,
            responseJson: [
                'onesignal' => $json,
            ],
        );
    }

    /**
     * @return ChannelSendResult
     */
    private function skipped(string $code, string $message): ChannelSendResult
    {
        return new ChannelSendResult(
            status: NotificationDeliveryStatus::Skipped,
            responseJson: [
                'skipped' => true,
                'code' => $code,
                'message' => $message,
            ],
        );
    }
}
