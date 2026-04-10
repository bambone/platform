<?php

namespace App\Product\Mail;

use App\ContactChannels\ContactChannelRegistry;
use App\Mail\PlatformMarketingContactMail;
use App\Models\CrmRequest;
use App\Models\CrmRequestActivity;
use App\Product\Settings\ProductMailSettingsResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProductMailOrchestrator
{
    public function __construct(
        private readonly ProductMailSettingsResolver $mailSettings,
    ) {}

    /**
     * Queue staff notification for a platform-scoped inbound CRM request.
     */
    public function queuePlatformInboundNotification(CrmRequest $crm): void
    {
        $recipients = $this->mailSettings->resolvePlatformContactRecipients();
        if ($recipients === []) {
            Log::warning('product_mail.platform_contact: no recipients (email.contact_form_recipients / PLATFORM_MARKETING_CONTACT_TO / mail.from.address).', [
                'crm_request_id' => $crm->id,
            ]);

            return;
        }

        $payload = $this->payloadFromCrm($crm);

        foreach ($recipients as $to) {
            Mail::to($to)->queue(new PlatformMarketingContactMail($payload, $crm->id));
        }

        CrmRequestActivity::query()->create([
            'crm_request_id' => $crm->id,
            'type' => CrmRequestActivity::TYPE_MAIL_QUEUED,
            'meta' => [
                'recipients_count' => count($recipients),
                'mail_type' => 'platform_contact_notification',
            ],
            'actor_user_id' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromCrm(CrmRequest $crm): array
    {
        $payload = $crm->payload_json ?? [];

        $pref = (string) ($crm->preferred_contact_channel ?? '');

        return [
            'name' => $crm->name,
            'phone' => $crm->phone,
            'email' => $crm->email,
            'preferred_contact_channel' => $pref,
            'preferred_contact_label' => $pref !== '' ? ContactChannelRegistry::label($pref) : '',
            'visitor_contact_channels_json' => $crm->visitor_contact_channels_json,
            'message' => $crm->message ?? '',
            'intent' => $payload['intent'] ?? '',
            'intent_label' => $payload['intent_label'] ?? ($payload['intent'] ?? ''),
            'utm_source' => $crm->utm_source,
            'utm_medium' => $crm->utm_medium,
            'utm_campaign' => $crm->utm_campaign,
            'utm_content' => $crm->utm_content,
            'utm_term' => $crm->utm_term,
            'page_url' => $crm->landing_page ?? $crm->referrer,
            'ip' => $crm->ip,
            'crm_request_id' => $crm->id,
        ];
    }
}
