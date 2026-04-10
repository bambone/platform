<?php

namespace Tests\Feature\CRM;

use App\Models\CrmRequestActivity;
use App\Models\Lead;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class CrmRequestInboundFlowTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function postWithHost(string $host, string $path, array $data): TestResponse
    {
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $this->call('POST', 'http://'.$host.$path, $data);
    }

    public function test_platform_contact_creates_crm_with_null_tenant_and_initial_activity(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Mail::fake();
        config(['mail.from.address' => 'ops@example.test']);

        $response = $this->postWithHost('apex.test', '/contact', [
            'name' => 'Inbound Platform',
            'phone' => '+79990001122',
            'email' => 'inbound-platform@example.test',
            'preferred_contact_channel' => 'phone',
            'message' => 'Message long enough for validation rules.',
            'intent' => 'launch',
            'company_site' => '',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('crm_requests', [
            'tenant_id' => null,
            'request_type' => 'platform_contact',
            'source' => 'platform_marketing_contact',
        ]);

        $crmId = (int) DB::table('crm_requests')->whereNull('tenant_id')->value('id');
        $this->assertGreaterThan(0, $crmId);

        $this->assertDatabaseHas('crm_request_activities', [
            'crm_request_id' => $crmId,
            'type' => CrmRequestActivity::TYPE_INBOUND_RECEIVED,
        ]);
    }

    public function test_tenant_api_lead_creates_scoped_crm_lead_and_initial_activity(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Mail::fake();

        $tenant = $this->createTenantWithActiveDomain('tinbound');

        $response = $this->postWithHost($this->tenancyHostForSlug('tinbound'), '/api/leads', [
            'name' => 'Tenant Lead',
            'phone' => '+79995554433',
            'email' => 'tenant-lead-flow@example.test',
            'comment' => 'Need a bike',
            'source' => 'booking_form',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $crmId = (int) $response->json('crm_request_id');
        $this->assertGreaterThan(0, $crmId);

        $this->assertDatabaseHas('crm_requests', [
            'id' => $crmId,
            'tenant_id' => $tenant->id,
            'request_type' => 'tenant_booking',
        ]);

        $this->assertDatabaseHas('crm_request_activities', [
            'crm_request_id' => $crmId,
            'type' => CrmRequestActivity::TYPE_INBOUND_RECEIVED,
        ]);

        $this->assertDatabaseHas('leads', [
            'tenant_id' => $tenant->id,
            'crm_request_id' => $crmId,
            'email' => 'tenant-lead-flow@example.test',
        ]);
    }

    public function test_lead_payload_cannot_spoof_tenant_id(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Mail::fake();

        $tenantA = $this->createTenantWithActiveDomain('tspoofa');
        $tenantB = $this->createTenantWithActiveDomain('tspoofb');

        $response = $this->call('POST', 'http://'.$this->tenancyHostForSlug('tspoofa').'/api/leads', [
            'name' => 'Spoof',
            'phone' => '+79991112233',
            'tenant_id' => $tenantB->id,
            'crm_fake' => 'x',
        ]);

        $response->assertOk();

        $leadId = (int) $response->json('lead_id');
        $lead = Lead::query()->findOrFail($leadId);
        $this->assertSame($tenantA->id, $lead->tenant_id);
        $this->assertSame($tenantA->id, $lead->crmRequest?->tenant_id);
    }
}
