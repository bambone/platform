<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\ContactChannels\ContactChannelType;
use App\ContactChannels\VisitorContactPayloadBuilder;
use App\Http\Requests\StoreContactInquiryRequest;
use App\Models\PageSection;
use App\Product\CRM\Actions\CreateCrmRequestFromPublicForm;
use App\Product\CRM\DTO\PublicInboundContext;
use App\Product\CRM\DTO\PublicInboundSubmission;
use App\Services\PublicSite\ContactInquiryFormPresenter;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

final class ContactInquiryController extends Controller
{
    public function store(
        StoreContactInquiryRequest $request,
        CreateCrmRequestFromPublicForm $createCrmRequest,
        VisitorContactPayloadBuilder $contactPayloadBuilder,
    ): JsonResponse {
        $tenant = currentTenant();
        abort_if($tenant === null, 404);

        $honeypot = trim((string) $request->input('website', ''));
        if ($honeypot !== '') {
            Log::warning('contact_inquiry_honeypot_triggered', [
                'tenant_id' => $tenant->id,
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Спасибо! Мы получили ваше сообщение и свяжемся с вами.',
            ]);
        }

        $section = PageSection::query()
            ->whereKey((int) $request->validated('page_section_id'))
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $cfg = is_array($section->data_json) ? $section->data_json : [];
        $requireServiceForPayload = ContactInquiryFormPresenter::sectionRequiresServiceSelector($cfg, $tenant);
        $successMessage = trim((string) ($cfg['success_message'] ?? ''));
        if ($successMessage === '') {
            $successMessage = 'Спасибо! Мы получили ваше сообщение и свяжемся с вами.';
        }

        $rateKey = 'contact-inquiry:'.$tenant->id.':'.($request->ip() ?? '0');
        if (RateLimiter::tooManyAttempts($rateKey, 8)) {
            Log::notice('contact_inquiry_rate_limited', [
                'tenant_id' => $tenant->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Слишком много отправок. Подождите минуту и попробуйте снова.',
            ], 429);
        }
        RateLimiter::hit($rateKey, 60);

        $validated = $request->validated();

        $showPreferred = (bool) ($cfg['show_preferred_channel'] ?? true);
        $preferredChannel = $showPreferred
            ? (string) $validated['preferred_contact_channel']
            : ContactChannelType::Phone->value;
        $preferredValue = $showPreferred ? ($validated['preferred_contact_value'] ?? null) : null;

        $contact = $contactPayloadBuilder->build($tenant->id, [
            'phone' => $validated['phone'],
            'preferred_contact_channel' => $preferredChannel,
            'preferred_contact_value' => $preferredValue,
        ]);

        $showEmail = (bool) ($cfg['show_email'] ?? true);
        $email = $showEmail ? ($validated['email'] ?? null) : null;
        if ($email === '') {
            $email = null;
        }

        $submission = new PublicInboundSubmission(
            requestType: 'contact_page_inquiry',
            name: $validated['name'],
            phone: $validated['phone'],
            email: $email,
            message: $validated['message'],
            source: 'contacts_page',
            channel: 'web',
            payloadJson: array_filter([
                'source_type' => 'contacts_form',
                'source_path' => '/contacts',
                'page_section_id' => $section->id,
                'inquiry_service_slug' => ($requireServiceForPayload && filled($validated['inquiry_service_slug'] ?? null))
                    ? (string) $validated['inquiry_service_slug']
                    : null,
            ], static fn ($v) => $v !== null),
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

        return response()->json([
            'success' => true,
            'message' => $successMessage,
            'lead_word' => $leadWord,
            'lead_id' => $lead->id,
            'crm_request_id' => $result->crmRequest->id,
        ]);
    }
}
