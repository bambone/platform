<?php

namespace Tests\Feature\Tenant;

use App\Models\CrmRequest;
use App\Models\TenantSetting;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

class ExpertPrInquiryTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    private function host(): string
    {
        $this->createTenantWithActiveDomain('expertpr', [
            'theme_key' => 'expert_pr',
            'locale' => 'en',
        ]);

        return $this->tenancyHostForSlug('expertpr');
    }

    public function test_expert_pr_email_only_inquiry_creates_crm_request(): void
    {
        $host = $this->host();

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Alex',
            'phone' => '',
            'contact_email' => 'lead@example.org',
            'goal_text' => 'Need media outreach for Q3 launch.',
            'preferred_contact_channel' => 'phone',
            'privacy_accepted' => true,
            'briefing_website' => 'example.com',
        ])->assertOk()->assertJsonPath('success', true);

        $crm = CrmRequest::query()->where('request_type', 'expert_service_inquiry')->first();
        $this->assertNotNull($crm);
        $this->assertSame('email', $crm->preferred_contact_channel);
        $this->assertSame('lead@example.org', $crm->preferred_contact_value);
        $this->assertSame('', (string) $crm->phone);

        $lead = \App\Models\Lead::query()->where('crm_request_id', $crm->id)->first();
        $this->assertNotNull($lead);
        $this->assertSame('', $lead->phone);
    }

    public function test_expert_pr_phone_only_inquiry_succeeds(): void
    {
        $host = $this->host();

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Alex',
            'phone' => '+12025550123',
            'contact_email' => '',
            'goal_text' => 'Need a call-back.',
            'preferred_contact_channel' => 'phone',
            'privacy_accepted' => true,
        ])->assertOk()->assertJsonPath('success', true);

        $crm = CrmRequest::query()->where('request_type', 'expert_service_inquiry')->first();
        $this->assertNotNull($crm);
        $this->assertNotNull($crm->phone);
    }

    public function test_expert_pr_missing_phone_and_email_returns_validation_error(): void
    {
        $host = $this->host();

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Alex',
            'phone' => '',
            'contact_email' => '',
            'goal_text' => 'Test',
            'preferred_contact_channel' => 'phone',
            'privacy_accepted' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_expert_pr_telegram_invalid_returns_english_error(): void
    {
        $slug = 'expertprtgtg';
        $tenant = $this->createTenantWithActiveDomain($slug, [
            'theme_key' => 'expert_pr',
            'locale' => 'en',
        ]);
        TenantSetting::setForTenant((int) $tenant->id, 'contacts.telegram', 'fixture_handle', 'string');
        $this->flushTenantHostCache($this->tenancyHostForSlug($slug));
        $host = $this->tenancyHostForSlug($slug);

        $res = $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Alex',
            'phone' => '+12025550123',
            'contact_email' => '',
            'goal_text' => 'Test',
            'preferred_contact_channel' => 'telegram',
            'preferred_contact_value' => '@@bad',
            'privacy_accepted' => true,
        ]);

        $res->assertStatus(422);
        $errors = json_encode($res->json('errors') ?? []);
        $this->assertStringContainsStringIgnoringCase('Telegram', $errors);
    }

    public function test_expert_pr_privacy_required(): void
    {
        $host = $this->host();

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Alex',
            'phone' => '',
            'contact_email' => 'lead@example.org',
            'goal_text' => 'Test',
            'preferred_contact_channel' => 'phone',
        ])->assertStatus(422)->assertJsonValidationErrors(['privacy_accepted']);
    }

    public function test_expert_pr_briefing_website_without_scheme_normalizes(): void
    {
        $host = $this->host();

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Alex',
            'phone' => '',
            'contact_email' => 'lead@example.org',
            'goal_text' => 'Test',
            'preferred_contact_channel' => 'phone',
            'privacy_accepted' => true,
            'briefing_website' => 'www.example.org/path',
        ])->assertOk();

        $crm = CrmRequest::query()->where('request_type', 'expert_service_inquiry')->first();
        $this->assertNotNull($crm);
        $payload = $crm->payload_json;
        $this->assertIsArray($payload);
        $this->assertSame('https://www.example.org/path', $payload['briefing_website'] ?? null);
    }

    public function test_expert_pr_rejects_preferred_email_when_phone_valid(): void
    {
        $host = $this->host();

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Alex',
            'phone' => '+12025550123',
            'contact_email' => 'lead@example.org',
            'goal_text' => 'Need a call.',
            'preferred_contact_channel' => 'email',
            'preferred_contact_value' => 'other@example.org',
            'privacy_accepted' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['preferred_contact_channel']);
    }

    public function test_expert_pr_privacy_acceptance_stored_on_lead(): void
    {
        $host = $this->host();

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'name' => 'Alex',
            'phone' => '',
            'contact_email' => 'lead@example.org',
            'goal_text' => 'Brief',
            'preferred_contact_channel' => 'phone',
            'privacy_accepted' => true,
        ])->assertOk()->assertJsonPath('success', true);

        $lead = \App\Models\Lead::query()->first();
        $this->assertNotNull($lead);
        $accept = $lead->legal_acceptances_json ?? null;
        $this->assertIsArray($accept);
        $this->assertSame('privacy_policy', $accept[0]['type'] ?? null);
    }

    public function test_honeypot_triggers_before_validation(): void
    {
        $host = $this->host();

        $this->postJson('http://'.$host.'/api/tenant/expert-inquiry', [
            'website' => 'http://spam.test',
        ])->assertOk()->assertJsonPath('success', true);
    }
}
