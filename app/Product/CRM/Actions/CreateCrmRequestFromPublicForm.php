<?php

namespace App\Product\CRM\Actions;

use App\Models\CrmRequest;
use App\Models\CrmRequestActivity;
use App\Models\Lead;
use App\Product\CRM\CrmRequestCreationResult;
use App\Product\CRM\DTO\PublicInboundContext;
use App\Product\CRM\DTO\PublicInboundSubmission;
use App\Product\Mail\ProductMailOrchestrator;
use Illuminate\Support\Facades\DB;

/**
 * Единый контракт входа с публичных форм: context + submission → сначала {@see CrmRequest}, затем optional downstream + почта/activity внутри ядра.
 *
 * Контроллеры форм не должны дублировать порядок «создать сущность → письмо → лог» вне этого use case.
 */
final class CreateCrmRequestFromPublicForm
{
    public function __construct(
        private readonly ProductMailOrchestrator $mailOrchestrator,
    ) {}

    public function handle(PublicInboundContext $context, PublicInboundSubmission $submission): CrmRequestCreationResult
    {
        return DB::transaction(function () use ($context, $submission): CrmRequestCreationResult {
            $crm = CrmRequest::query()->create([
                'tenant_id' => $context->tenantId,
                'name' => $submission->name,
                'phone' => $submission->phone,
                'email' => $submission->email,
                'message' => $submission->message ?? '',
                'request_type' => $submission->requestType,
                'source' => $submission->source,
                'channel' => $submission->channel,
                'pipeline' => 'inbound',
                'status' => CrmRequest::STATUS_NEW,
                'priority' => CrmRequest::PRIORITY_NORMAL,
                'utm_source' => $submission->utmSource,
                'utm_medium' => $submission->utmMedium,
                'utm_campaign' => $submission->utmCampaign,
                'utm_content' => $submission->utmContent,
                'utm_term' => $submission->utmTerm,
                'referrer' => $submission->referrer,
                'landing_page' => $submission->landingPage,
                'ip' => $submission->ip,
                'user_agent' => $submission->userAgent,
                'payload_json' => $submission->payloadJson !== [] ? $submission->payloadJson : null,
                'last_activity_at' => now(),
            ]);

            CrmRequestActivity::query()->create([
                'crm_request_id' => $crm->id,
                'type' => CrmRequestActivity::TYPE_INBOUND_RECEIVED,
                'meta' => [
                    'request_type' => $submission->requestType,
                    'channel' => $submission->channel,
                ],
                'actor_user_id' => null,
            ]);

            $lead = null;
            if (! $context->isPlatformScope && $context->tenantId !== null) {
                $lead = $this->createDownstreamLead($crm, $submission);
            }

            if ($context->isPlatformScope) {
                $this->mailOrchestrator->queuePlatformInboundNotification($crm);
            }

            return new CrmRequestCreationResult(crmRequest: $crm, lead: $lead);
        });
    }

    private function createDownstreamLead(CrmRequest $crm, PublicInboundSubmission $submission): Lead
    {
        $extras = $submission->payloadJson;

        return Lead::query()->create([
            'tenant_id' => $crm->tenant_id,
            'crm_request_id' => $crm->id,
            'name' => $submission->name,
            'phone' => $submission->phone,
            'email' => $submission->email,
            'comment' => $submission->message,
            'motorcycle_id' => isset($extras['motorcycle_id']) ? (int) $extras['motorcycle_id'] : null,
            'rental_date_from' => $extras['rental_date_from'] ?? null,
            'rental_date_to' => $extras['rental_date_to'] ?? null,
            'source' => $submission->source ?? 'booking_form',
            'page_url' => $submission->landingPage,
            'utm_source' => $submission->utmSource,
            'utm_medium' => $submission->utmMedium,
            'utm_campaign' => $submission->utmCampaign,
            'utm_content' => $submission->utmContent,
            'utm_term' => $submission->utmTerm,
            'status' => 'new',
        ]);
    }
}
