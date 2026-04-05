<?php

namespace Tests\Feature\Notifications\Drivers;

use App\Jobs\DispatchNotificationDeliveryJob;
use App\Models\NotificationDelivery;
use App\Models\NotificationDestination;
use App\Models\NotificationEvent;
use App\NotificationCenter\Drivers\TelegramNotificationDriver;
use App\NotificationCenter\NotificationChannelDriverFactory;
use App\NotificationCenter\NotificationChannelType;
use App\NotificationCenter\NotificationDeliveryStatus;
use App\NotificationCenter\NotificationDestinationStatus;
use App\NotificationCenter\NotificationPayloadDto;
use App\Services\CurrentTenantManager;
use App\Services\Platform\PlatformNotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

class TelegramNotificationDriverTest extends TestCase
{
    use NotificationTestHelpers;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Http::fake();
        if (class_exists(PlatformNotificationSettings::class)) {
            $settings = app(PlatformNotificationSettings::class);
            $settings->setChannelEnabled('telegram', true);
            $settings->setTelegramBotToken('test-bot-token');
        }
        parent::tearDown();
    }

    public function test_successful_send_writes_provider_response(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 555]], 200),
        ]);

        $tenant = $this->createNotificationTenant();
        app(PlatformNotificationSettings::class)->setTelegramBotToken('test-bot-token');

        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'TG',
            'type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => ['chat_id' => '12345'],
        ]);

        $event = NotificationEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => null,
            'payload_json' => (new NotificationPayloadDto('Hi', 'Body', null, null, []))->toArray(),
            'occurred_at' => now(),
        ]);

        $delivery = NotificationDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'destination_id' => $dest->id,
            'channel_type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        app(CurrentTenantManager::class)->setTenant($tenant);
        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        $job->handle(app(CurrentTenantManager::class), app(NotificationChannelDriverFactory::class));

        $delivery->refresh();
        $this->assertSame(NotificationDeliveryStatus::Sent->value, $delivery->status);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNull($delivery->delivered_at);
        Http::assertSentCount(1);
    }

    public function test_send_truncates_message_to_telegram_limit(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $tenant = $this->createNotificationTenant();
        app(PlatformNotificationSettings::class)->setTelegramBotToken('test-bot-token');

        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'TG',
            'type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => ['chat_id' => '1'],
        ]);

        $long = str_repeat('а', 5000);
        $event = NotificationEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => null,
            'payload_json' => (new NotificationPayloadDto($long, $long, null, null, []))->toArray(),
            'occurred_at' => now(),
        ]);

        $delivery = NotificationDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'destination_id' => $dest->id,
            'channel_type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        app(CurrentTenantManager::class)->setTenant($tenant);
        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        $job->handle(app(CurrentTenantManager::class), app(NotificationChannelDriverFactory::class));

        Http::assertSent(function (Request $request): bool {
            $payload = json_decode((string) $request->body(), true);
            $text = is_array($payload) ? (string) ($payload['text'] ?? '') : '';

            return $text !== '' && mb_strlen($text) <= 4096;
        });
    }

    public function test_kill_switch_skips_dispatch_without_external_http(): void
    {
        Http::fake();

        $tenant = $this->createNotificationTenant();
        app(PlatformNotificationSettings::class)->setTelegramBotToken('x');
        app(PlatformNotificationSettings::class)->setChannelEnabled('telegram', false);

        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'TG',
            'type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => ['chat_id' => '1'],
        ]);

        $event = NotificationEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => null,
            'payload_json' => (new NotificationPayloadDto('Hi', 'Body', null, null, []))->toArray(),
            'occurred_at' => now(),
        ]);

        $delivery = NotificationDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'destination_id' => $dest->id,
            'channel_type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        app(CurrentTenantManager::class)->setTenant($tenant);
        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        $job->handle(app(CurrentTenantManager::class), app(NotificationChannelDriverFactory::class));

        Http::assertNothingSent();
        $delivery->refresh();
        $this->assertSame(NotificationDeliveryStatus::Skipped->value, $delivery->status);

        app(PlatformNotificationSettings::class)->setChannelEnabled('telegram', true);
    }

    public function test_driver_throws_when_bot_token_not_configured(): void
    {
        Http::fake();
        app(PlatformNotificationSettings::class)->setTelegramBotToken(null);

        $tenant = $this->createNotificationTenant();
        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'TG',
            'type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => ['chat_id' => '1'],
        ]);
        $event = NotificationEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => null,
            'payload_json' => (new NotificationPayloadDto('T', 'B', null, null, []))->toArray(),
            'occurred_at' => now(),
        ]);
        $delivery = NotificationDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'destination_id' => $dest->id,
            'channel_type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Telegram bot token');

        app(TelegramNotificationDriver::class)->send($delivery, $event, $dest);
    }

    public function test_driver_throws_when_chat_id_missing(): void
    {
        Http::fake();
        app(PlatformNotificationSettings::class)->setTelegramBotToken('x');

        $tenant = $this->createNotificationTenant();
        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'TG',
            'type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => ['chat_id' => '  '],
        ]);
        $event = NotificationEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => null,
            'payload_json' => (new NotificationPayloadDto('T', 'B', null, null, []))->toArray(),
            'occurred_at' => now(),
        ]);
        $delivery = NotificationDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'destination_id' => $dest->id,
            'channel_type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(TelegramNotificationDriver::class)->send($delivery, $event, $dest);
    }

    public function test_telegram_api_error_marks_delivery_failed(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad'], 502),
        ]);

        $tenant = $this->createNotificationTenant();
        app(PlatformNotificationSettings::class)->setTelegramBotToken('tok');

        $dest = NotificationDestination::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'name' => 'TG',
            'type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDestinationStatus::Verified->value,
            'is_shared' => true,
            'config_json' => ['chat_id' => '1'],
        ]);
        $event = NotificationEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'event_key' => 'crm_request.created',
            'subject_type' => 'CrmRequest',
            'subject_id' => 1,
            'severity' => 'normal',
            'dedupe_key' => null,
            'payload_json' => (new NotificationPayloadDto('T', 'B', null, null, []))->toArray(),
            'occurred_at' => now(),
        ]);
        $delivery = NotificationDelivery::factory()->create([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'destination_id' => $dest->id,
            'channel_type' => NotificationChannelType::Telegram->value,
            'status' => NotificationDeliveryStatus::Queued->value,
            'queued_at' => now(),
        ]);

        app(CurrentTenantManager::class)->setTenant($tenant);
        $job = new DispatchNotificationDeliveryJob((int) $delivery->id);
        try {
            $job->handle(app(CurrentTenantManager::class), app(NotificationChannelDriverFactory::class));
        } catch (\Throwable) {
        }

        $delivery->refresh();
        $this->assertSame(NotificationDeliveryStatus::Failed->value, $delivery->status);
    }
}
