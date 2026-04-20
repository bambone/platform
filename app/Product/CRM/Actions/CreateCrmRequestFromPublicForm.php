<?php

namespace App\Product\CRM\Actions;

use App\Models\CrmRequest;
use App\Models\CrmRequestActivity;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\TenantPushEventPreference;
use App\NotificationCenter\NotificationEventRecorder;
use App\NotificationCenter\NotificationRoutingContext;
use App\NotificationCenter\Presenters\CrmRequestNotificationPresenter;
use App\Product\CRM\CrmRequestCreationResult;
use App\Product\CRM\DTO\PublicInboundContext;
use App\Product\CRM\DTO\PublicInboundSubmission;
use App\Product\Mail\ProductMailOrchestrator;
use App\TenantPush\TenantPushCrmRequestRecipientResolver;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushProviderStatus;
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
        private readonly NotificationEventRecorder $notificationRecorder,
        private readonly CrmRequestNotificationPresenter $crmNotifications,
        private readonly TenantPushFeatureGate $tenantPushFeatureGate,
        private readonly TenantPushCrmRequestRecipientResolver $tenantPushCrmRequestRecipientResolver,
    ) {}

    public function handle(PublicInboundContext $context, PublicInboundSubmission $submission): CrmRequestCreationResult
    {
        return DB::transaction(function () use ($context, $submission): CrmRequestCreationResult {
            $crm = CrmRequest::query()->create([
                'tenant_id' => $context->tenantId,
                'name' => $submission->name,
                'phone' => $submission->phone,
                'preferred_contact_channel' => $submission->preferredContactChannel,
                'preferred_contact_value' => $submission->preferredContactValue,
                'visitor_contact_channels_json' => $submission->visitorContactChannelsJson,
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

            if (! $context->isPlatformScope && $context->tenantId !== null) {
                $crmId = (int) $crm->id;
                $tenantId = (int) $context->tenantId;
                DB::afterCommit(function () use ($crmId, $tenantId): void {
                    $fresh = CrmRequest::query()->find($crmId);
                    $tenant = Tenant::query()->find($tenantId);
                    if ($fresh === null || $tenant === null) {
                        return;
                    }

                    $payload = $this->crmNotifications->payloadForCreated($tenant, $fresh);
                    $routing = $this->notificationRoutingContextForTenant($tenant);
                    $this->notificationRecorder->record(
                        $tenantId,
                        'crm_request.created',
                        class_basename(CrmRequest::class),
                        $crmId,
                        $payload,
                        routingContext: $routing,
                    );
                });
            }

            return new CrmRequestCreationResult(crmRequest: $crm, lead: $lead);
        });
    }

    private function notificationRoutingContextForTenant(Tenant $tenant): ?NotificationRoutingContext
    {
        $gate = $this->tenantPushFeatureGate->evaluate($tenant);
        if (! $gate->isFeatureEntitled()) {
            return null;
        }

        $settings = $this->tenantPushFeatureGate->findSettings($tenant);
        if ($settings === null) {
            return null;
        }

        if (! $settings->is_push_enabled) {
            return null;
        }

        if ($settings->providerStatusEnum() !== TenantPushProviderStatus::Verified) {
            return null;
        }

        $pref = TenantPushEventPreference::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_key', 'crm_request.created')
            ->first();

        if ($pref === null || ! $pref->is_enabled) {
            return null;
        }

        $ids = $this->tenantPushCrmRequestRecipientResolver->resolveOnesignalRecipientUserIds($tenant);
        if ($ids === []) {
            return null;
        }

        return NotificationRoutingContext::forUsers($ids);
    }

    private function createDownstreamLead(CrmRequest $crm, PublicInboundSubmission $submission): Lead
    {
        $extras = $submission->payloadJson;

        return Lead::query()->create([
            'tenant_id' => $crm->tenant_id,
            'crm_request_id' => $crm->id,
            'name' => $submission->name,
            'phone' => $submission->phone,
            'preferred_contact_channel' => $submission->preferredContactChannel,
            'preferred_contact_value' => $submission->preferredContactValue,
            'visitor_contact_channels_json' => $submission->visitorContactChannelsJson,
            'legal_acceptances_json' => $submission->legalAcceptancesJson,
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
            'status' => $submission->leadInitialStatus ?? 'new',
        ]);
    }
}
