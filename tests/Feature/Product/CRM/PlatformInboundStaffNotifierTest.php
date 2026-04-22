<?php

namespace Tests\Feature\Product\CRM;

use App\Jobs\SendPlatformContactTelegramNotification;
use App\Models\CrmRequest;
use App\Models\CrmRequestActivity;
use App\Product\CRM\Notifications\PlatformInboundStaffNotifier;
use App\Services\Platform\PlatformNotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class PlatformInboundStaffNotifierTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_queues_one_job_per_chat_id_and_writes_activity(): void
    {
        Bus::fake();

        $settings = app(PlatformNotificationSettings::class);
        $settings->setChannelEnabled('telegram', true);
        $settings->setTelegramBotToken('test-token');
        $settings->setPlatformContactTelegramChatIds(' 111 , 111 , -1001234567890 ');

        $crm = CrmRequest::query()->create([
            'tenant_id' => null,
            'name' => 'A',
            'phone' => '+79990001122',
            'email' => 'a@example.test',
            'message' => 'Hello',
            'request_type' => 'platform_contact',
            'source' => 'platform_marketing_contact',
            'channel' => 'web',
            'pipeline' => 'inbound',
            'status' => CrmRequest::STATUS_NEW,
            'priority' => CrmRequest::PRIORITY_NORMAL,
            'payload_json' => ['intent' => 'demo', 'intent_label' => 'Demo'],
            'last_activity_at' => now(),
        ]);

        app(PlatformInboundStaffNotifier::class)->queueForPlatformContact($crm);

        Bus::assertDispatchedTimes(SendPlatformContactTelegramNotification::class, 2);

        $this->assertDatabaseHas('crm_request_activities', [
            'crm_request_id' => $crm->id,
            'type' => CrmRequestActivity::TYPE_TELEGRAM_QUEUED,
        ]);

        $row = CrmRequestActivity::query()
            ->where('crm_request_id', $crm->id)
            ->where('type', CrmRequestActivity::TYPE_TELEGRAM_QUEUED)
            ->first();
        $this->assertSame(2, $row->meta['chat_ids_count'] ?? null);
        $this->assertSame('telegram', $row->meta['channel'] ?? null);
    }

    public function test_platform_contact_with_tenant_id_no_ops(): void
    {
        Bus::fake();
        $tenant = $this->createTenantWithActiveDomain('tgskip');

        $settings = app(PlatformNotificationSettings::class);
        $settings->setChannelEnabled('telegram', true);
        $settings->setTelegramBotToken('test-token');
        $settings->setPlatformContactTelegramChatIds('123');

        $crm = CrmRequest::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'A',
            'phone' => '+1',
            'message' => 'x',
            'request_type' => 'platform_contact',
            'source' => 'err',
            'channel' => 'web',
            'pipeline' => 'inbound',
            'status' => CrmRequest::STATUS_NEW,
            'priority' => CrmRequest::PRIORITY_NORMAL,
            'last_activity_at' => now(),
        ]);

        app(PlatformInboundStaffNotifier::class)->queueForPlatformContact($crm);

        Bus::assertNothingDispatched();
        $this->assertSame(0, CrmRequestActivity::query()->where('type', CrmRequestActivity::TYPE_TELEGRAM_QUEUED)->count());
    }

    public function test_no_dispatch_when_chat_ids_empty(): void
    {
        Bus::fake();

        $settings = app(PlatformNotificationSettings::class);
        $settings->setChannelEnabled('telegram', true);
        $settings->setTelegramBotToken('test-token');
        $settings->setPlatformContactTelegramChatIds(null);

        $crm = CrmRequest::query()->create([
            'tenant_id' => null,
            'name' => 'A',
            'phone' => '+1',
            'message' => 'x',
            'request_type' => 'platform_contact',
            'source' => 'platform_marketing_contact',
            'channel' => 'web',
            'pipeline' => 'inbound',
            'status' => CrmRequest::STATUS_NEW,
            'priority' => CrmRequest::PRIORITY_NORMAL,
            'last_activity_at' => now(),
        ]);

        app(PlatformInboundStaffNotifier::class)->queueForPlatformContact($crm);

        Bus::assertNothingDispatched();
        $this->assertDatabaseMissing('crm_request_activities', [
            'crm_request_id' => $crm->id,
            'type' => CrmRequestActivity::TYPE_TELEGRAM_QUEUED,
        ]);
    }
}
