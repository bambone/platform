<?php

namespace Tests\Feature\CRM;

use App\Jobs\SendPlatformContactTelegramNotification;
use App\Mail\PlatformMarketingContactMail;
use App\Models\CrmRequest;
use App\Models\CrmRequestActivity;
use App\Models\Lead;
use App\Product\CRM\Actions\CreateCrmRequestFromPublicForm;
use App\Product\CRM\DTO\PublicInboundContext;
use App\Product\CRM\DTO\PublicInboundSubmission;
use App\Product\Mail\ProductMailOrchestrator;
use App\Services\Platform\PlatformNotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class CreateCrmRequestFromPublicFormTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_platform_handle_creates_inbound_activity_and_queues_mail(): void
    {
        Mail::fake();
        config(['mail.from.address' => 'ops@example.test']);

        $submission = new PublicInboundSubmission(
            requestType: 'platform_contact',
            name: 'Action User',
            phone: '+79990001122',
            email: 'action@example.test',
            message: 'Hello',
            source: 'platform_marketing_contact',
            channel: 'web',
            payloadJson: ['intent' => 'demo'],
        );

        $result = app(CreateCrmRequestFromPublicForm::class)->handle(PublicInboundContext::platform(), $submission);

        $this->assertNull($result->crmRequest->tenant_id);
        $this->assertNull($result->lead);
        $this->assertSame(CrmRequest::PRIORITY_NORMAL, $result->crmRequest->priority);
        $this->assertNotNull($result->crmRequest->last_activity_at);

        $this->assertDatabaseHas('crm_request_activities', [
            'crm_request_id' => $result->crmRequest->id,
            'type' => CrmRequestActivity::TYPE_INBOUND_RECEIVED,
        ]);

        Mail::assertQueued(PlatformMarketingContactMail::class);
    }

    /**
     * Asserts the full stack including {@see \Illuminate\Support\Facades\DB::afterCommit()} inside
     * {@see CreateCrmRequestFromPublicForm}. If this flakes under another DB driver, CI transaction
     * wrapping, or outer transaction level, rely on {@see \Tests\Feature\Product\CRM\PlatformInboundStaffNotifierTest}
     * for notifier behavior and treat this as an integration guard only.
     */
    public function test_platform_dispatches_telegram_jobs_when_provider_configured(): void
    {
        Mail::fake();
        Bus::fake();
        config(['mail.from.address' => 'ops@example.test']);

        $settings = app(PlatformNotificationSettings::class);
        $settings->setChannelEnabled('telegram', true);
        $settings->setTelegramBotToken('tok');
        $settings->setPlatformContactTelegramChatIds('999,-100111');

        $submission = new PublicInboundSubmission(
            requestType: 'platform_contact',
            name: 'Tg User',
            phone: '+79990001122',
            email: 'tg@example.test',
            message: 'Hello telegram',
            source: 'platform_marketing_contact',
            channel: 'web',
            payloadJson: ['intent' => 'demo'],
        );

        $result = app(CreateCrmRequestFromPublicForm::class)->handle(PublicInboundContext::platform(), $submission);

        Bus::assertDispatchedTimes(SendPlatformContactTelegramNotification::class, 2);

        $this->assertDatabaseHas('crm_request_activities', [
            'crm_request_id' => $result->crmRequest->id,
            'type' => CrmRequestActivity::TYPE_TELEGRAM_QUEUED,
        ]);
    }

    public function test_tenant_handle_creates_lead_linked_to_crm_and_inbound_activity(): void
    {
        Mail::fake();

        $tenant = $this->createTenantWithActiveDomain('taction');

        $submission = new PublicInboundSubmission(
            requestType: 'tenant_booking',
            name: 'Renter',
            phone: '+79993332211',
            email: 'renter@example.test',
            message: 'Booking ask',
            source: 'booking_form',
            channel: 'web',
            payloadJson: ['motorcycle_id' => null],
            landingPage: 'https://example.test/order',
        );

        $result = app(CreateCrmRequestFromPublicForm::class)->handle(PublicInboundContext::tenant($tenant->id), $submission);

        $this->assertSame($tenant->id, $result->crmRequest->tenant_id);
        $this->assertNotNull($result->lead);
        $this->assertSame($result->crmRequest->id, $result->lead->crm_request_id);

        $this->assertDatabaseHas('crm_request_activities', [
            'crm_request_id' => $result->crmRequest->id,
            'type' => CrmRequestActivity::TYPE_INBOUND_RECEIVED,
        ]);
    }

    public function test_platform_transaction_rollbacks_when_downstream_mail_fails(): void
    {
        $mock = Mockery::mock(ProductMailOrchestrator::class);
        $mock->shouldReceive('queuePlatformInboundNotification')
            ->once()
            ->andThrow(new \RuntimeException('downstream mail failure'));
        $this->app->instance(ProductMailOrchestrator::class, $mock);

        $submission = new PublicInboundSubmission(
            requestType: 'platform_contact',
            name: 'Rollback',
            phone: '+79990000000',
            email: null,
            message: 'x',
            source: 'platform_marketing_contact',
        );

        try {
            app(CreateCrmRequestFromPublicForm::class)->handle(PublicInboundContext::platform(), $submission);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('downstream mail failure', $e->getMessage());
        }

        $this->assertSame(0, CrmRequest::query()->count());
        $this->assertSame(0, CrmRequestActivity::query()->count());
        $this->assertSame(0, Lead::query()->count());
    }
}
