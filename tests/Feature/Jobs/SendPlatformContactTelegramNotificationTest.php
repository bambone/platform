<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendPlatformContactTelegramNotification;
use App\Models\CrmRequest;
use App\Services\Platform\PlatformNotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendPlatformContactTelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_plain_text_via_telegram_api(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]], 200),
        ]);

        $settings = app(PlatformNotificationSettings::class);
        $settings->setChannelEnabled('telegram', true);
        $settings->setTelegramBotToken('bot-token');

        $crm = CrmRequest::query()->create([
            'tenant_id' => null,
            'name' => 'Job User',
            'phone' => '+79991112233',
            'email' => 'job@example.test',
            'message' => 'Need demo',
            'request_type' => 'platform_contact',
            'source' => 'platform_marketing_contact',
            'channel' => 'web',
            'pipeline' => 'inbound',
            'status' => CrmRequest::STATUS_NEW,
            'priority' => CrmRequest::PRIORITY_NORMAL,
            'utm_source' => 'google',
            'last_activity_at' => now(),
        ]);

        $job = new SendPlatformContactTelegramNotification((int) $crm->id, '-1001234567890');
        $this->app->call([$job, 'handle']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $data = json_decode($request->body(), true);
            if (! is_array($data)) {
                return false;
            }

            return str_contains($request->url(), 'api.telegram.org')
                && ($data['chat_id'] ?? null) === '-1001234567890'
                && ($data['text'] ?? '') !== ''
                && ($data['disable_web_page_preview'] ?? false) === true
                && ! array_key_exists('parse_mode', $data);
        });
    }

    public function test_skips_when_crm_not_platform_contact(): void
    {
        Http::fake();

        $settings = app(PlatformNotificationSettings::class);
        $settings->setChannelEnabled('telegram', true);
        $settings->setTelegramBotToken('bot-token');

        $crm = CrmRequest::query()->create([
            'tenant_id' => null,
            'name' => 'X',
            'phone' => '+1',
            'message' => 'm',
            'request_type' => 'tenant_booking',
            'source' => 'booking_form',
            'channel' => 'web',
            'pipeline' => 'inbound',
            'status' => CrmRequest::STATUS_NEW,
            'priority' => CrmRequest::PRIORITY_NORMAL,
            'last_activity_at' => now(),
        ]);

        $job = new SendPlatformContactTelegramNotification((int) $crm->id, '123');
        $this->app->call([$job, 'handle']);

        Http::assertNothingSent();
    }
}
