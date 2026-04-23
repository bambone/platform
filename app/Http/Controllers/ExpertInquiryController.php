<?php

namespace App\Http\Controllers;

use App\ContactChannels\VisitorContactPayloadBuilder;
use App\Http\Requests\StoreExpertInquiryRequest;
use App\Product\CRM\Actions\CreateCrmRequestFromPublicForm;
use App\Product\CRM\DTO\PublicInboundContext;
use App\Product\CRM\DTO\PublicInboundSubmission;
use App\Tenant\Expert\BlackDuckPublicCrmRequestTypeResolver;
use App\Tenant\Expert\ExpertInquiryIntentResolver;
use App\Tenant\Expert\TenantEnrollmentCtaConfig;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

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

        $honeypot = trim((string) $request->input('website', ''));
        if ($honeypot !== '') {
            Log::warning('expert_inquiry_honeypot_triggered', [
                'tenant_id' => $tenant->id,
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Спасибо! Заявка отправлена. Мы свяжемся с вами.',
            ]);
        }

        $rateKey = 'expert-inquiry:'.$tenant->id.':'.($request->ip() ?? '0');
        if (RateLimiter::tooManyAttempts($rateKey, 8)) {
            Log::notice('expert_inquiry_rate_limited', [
                'tenant_id' => $tenant->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Слишком много отправок. Подождите минуту и попробуйте снова.',
            ], 429);
        }
        RateLimiter::hit($rateKey, 60);

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
            $expertDomain = $tenant->themeKey() === 'black_duck' ? 'vehicle_detailing' : 'driving_instruction';
        }

        $intentTags = $intentResolver->resolve($programSlug, $goal);

        $payloadJson = [
            'expert_domain' => $expertDomain,
            'intent_tags' => $intentTags,
            'goal_text' => $goal,
        ];
        if ($tenant->themeKey() === 'black_duck') {
            foreach ([
                'service_slug', 'service_group', 'vehicle_class', 'vehicle_make', 'vehicle_model', 'customer_goal',
            ] as $pk) {
                $v = $validated[$pk] ?? null;
                if (is_string($v) && trim($v) !== '') {
                    $payloadJson[$pk] = trim($v);
                }
            }
            if (array_key_exists('needs_confirmation', $validated)) {
                $payloadJson['needs_confirmation'] = (bool) $validated['needs_confirmation'];
            }
            $hint = trim((string) ($validated['crm_request_type'] ?? ''));
            if ($hint !== '') {
                $payloadJson['client_crm_type_hint'] = $hint;
            }
            $intent = trim((string) ($validated['inquiry_intent'] ?? ''));
            if ($intent !== '') {
                $payloadJson['inquiry_intent'] = $intent;
            }
        }
        if ($programSlug !== null) {
            $payloadJson['program_slug'] = $programSlug;
        }
        foreach (['preferred_schedule', 'district', 'has_own_car', 'transmission', 'has_license'] as $opt) {
            $v = $validated[$opt] ?? null;
            if ($v !== null && $v !== '') {
                $payloadJson[$opt] = $v;
            }
        }

        $sourceType = $validated['source_type'] ?? null;
        $isProgramEnrollment = $sourceType === 'program_enrollment';
        $isEnrollmentCta = $sourceType === 'enrollment_cta';
        $isInboundEnrollment = $isProgramEnrollment || $isEnrollmentCta;

        if ($isInboundEnrollment) {
            $payloadJson['source_type'] = (string) $sourceType;
            $spRaw = trim((string) ($validated['source_page'] ?? ''));
            if ($spRaw !== '') {
                $payloadJson['source_page'] = $spRaw;
            }
            $ctx = trim((string) ($validated['source_context'] ?? ''));
            if ($ctx !== '') {
                $payloadJson['source_context'] = $ctx;
            }
            if ($isProgramEnrollment) {
                $pid = $validated['program_id'] ?? null;
                if ($pid !== null && (int) $pid > 0) {
                    $payloadJson['program_id'] = (int) $pid;
                }
            }
        }

        $inboundSource = match ($sourceType) {
            'program_enrollment' => 'programs_page',
            'enrollment_cta' => 'expert_enrollment',
            default => 'expert_lead_form',
        };

        if ($tenant->themeKey() === 'black_duck') {
            $spLead = trim((string) ($validated['source_page'] ?? ''));
            if ($spLead !== '' && $inboundSource === 'expert_lead_form' && ! isset($payloadJson['source_page'])) {
                $payloadJson['source_page'] = $spLead;
            }
        }

        $requestType = 'expert_service_inquiry';
        if ($tenant->themeKey() === 'black_duck') {
            $requestType = app(BlackDuckPublicCrmRequestTypeResolver::class)->resolve((int) $tenant->id, $validated);
        }

        $submission = new PublicInboundSubmission(
            requestType: $requestType,
            name: $validated['name'],
            phone: $validated['phone'],
            email: null,
            message: $message,
            source: $inboundSource,
            channel: 'web',
            payloadJson: $payloadJson,
            utmSource: $validated['utm_source'] ?? null,
            utmMedium: $validated['utm_medium'] ?? null,
            utmCampaign: $validated['utm_campaign'] ?? null,
            utmContent: $validated['utm_content'] ?? null,
            utmTerm: $validated['utm_term'] ?? null,
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

        $responseMessage = 'Спасибо! Заявка отправлена. Мы свяжемся с вами.';
        if ($isInboundEnrollment) {
            $responseMessage = TenantEnrollmentCtaConfig::forCurrent()?->modalSuccessMessage() ?? $responseMessage;
        }

        return response()->json([
            'success' => true,
            'message' => $responseMessage,
            'lead_word' => $leadWord,
            'lead_id' => $lead->id,
            'crm_request_id' => $result->crmRequest->id,
        ]);
    }
}
