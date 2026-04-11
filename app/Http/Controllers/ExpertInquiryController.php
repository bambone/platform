<?php

namespace App\Http\Controllers;

use App\ContactChannels\VisitorContactPayloadBuilder;
use App\Http\Requests\StoreExpertInquiryRequest;
use App\Product\CRM\Actions\CreateCrmRequestFromPublicForm;
use App\Product\CRM\DTO\PublicInboundContext;
use App\Product\CRM\DTO\PublicInboundSubmission;
use App\Tenant\Expert\ExpertInquiryIntentResolver;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyService;
use Illuminate\Http\JsonResponse;

final class ExpertInquiryController extends Controller
{
    public function store(
        StoreExpertInquiryRequest $request,
        CreateCrmRequestFromPublicForm $createCrmRequest,
        VisitorContactPayloadBuilder $contactPayloadBuilder,
        ExpertInquiryIntentResolver $intentResolver,
    ): JsonResponse {
        $tenant = currentTenant();
        abort_if($tenant === null, 404);

        $validated = $request->validated();

        $contact = $contactPayloadBuilder->build($tenant->id, [
            'phone' => $validated['phone'],
            'preferred_contact_channel' => $validated['preferred_contact_channel'],
            'preferred_contact_value' => $validated['preferred_contact_value'] ?? null,
        ]);

        $goal = $validated['goal_text'];
        $comment = trim((string) ($validated['comment'] ?? ''));
        $message = $comment !== '' ? $goal."\n\n".$comment : $goal;

        $programSlug = isset($validated['program_slug']) ? trim((string) $validated['program_slug']) : '';
        $programSlug = $programSlug !== '' ? $programSlug : null;

        $expertDomain = trim((string) ($validated['expert_domain'] ?? ''));
        if ($expertDomain === '') {
            $expertDomain = 'driving_instruction';
        }

        $intentTags = $intentResolver->resolve($programSlug, $goal);

        $payloadJson = [
            'expert_domain' => $expertDomain,
            'intent_tags' => $intentTags,
            'goal_text' => $goal,
        ];
        if ($programSlug !== null) {
            $payloadJson['program_slug'] = $programSlug;
        }
        foreach (['preferred_schedule', 'district', 'has_own_car', 'transmission', 'has_license'] as $opt) {
            $v = $validated[$opt] ?? null;
            if ($v !== null && $v !== '') {
                $payloadJson[$opt] = $v;
            }
        }

        $submission = new PublicInboundSubmission(
            requestType: 'expert_service_inquiry',
            name: $validated['name'],
            phone: $validated['phone'],
            email: null,
            message: $message,
            source: 'expert_lead_form',
            channel: 'web',
            payloadJson: $payloadJson,
            landingPage: $validated['page_url'] ?? $request->header('referer'),
            referrer: $request->header('referer'),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            preferredContactChannel: $contact['preferred_contact_channel'],
            preferredContactValue: $contact['preferred_contact_value'],
            visitorContactChannelsJson: $contact['visitor_contact_channels_json'],
        );

        $result = $createCrmRequest->handle(PublicInboundContext::tenant($tenant->id), $submission);

        $lead = $result->lead;
        abort_if($lead === null, 500);

        $leadWord = app(TenantTerminologyService::class)->label($tenant, DomainTermKeys::LEAD);

        return response()->json([
            'success' => true,
            'message' => 'Спасибо! Заявка отправлена. Мы свяжемся с вами.',
            'lead_word' => $leadWord,
            'lead_id' => $lead->id,
            'crm_request_id' => $result->crmRequest->id,
        ]);
    }
}
