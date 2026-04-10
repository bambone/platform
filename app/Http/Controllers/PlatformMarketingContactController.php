<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlatformMarketingContactRequest;
use App\Product\CRM\Actions\CreateCrmRequestFromPublicForm;
use App\Product\CRM\DTO\PublicInboundContext;
use App\Product\CRM\DTO\PublicInboundSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

class PlatformMarketingContactController extends Controller
{
    public function store(PlatformMarketingContactRequest $request, CreateCrmRequestFromPublicForm $createCrmRequest): RedirectResponse
    {
        $intent = (string) ($request->validated('intent') ?? '');
        if ($intent === '') {
            $intent = (string) (config('platform_marketing.intent.launch') ?? 'launch');
        }

        $intentsMeta = config('platform_marketing.contact_page.intents', []);
        $intentMeta = is_array($intentsMeta[$intent] ?? null) ? $intentsMeta[$intent] : [];
        $intentLabel = (string) ($intentMeta['title'] ?? $intent);

        $contact = $request->resolvedContactPayload();

        $submission = new PublicInboundSubmission(
            requestType: 'platform_contact',
            name: $request->validated('name'),
            phone: $contact['phone'],
            email: $request->validated('email') ?: null,
            message: $request->validated('message'),
            source: 'platform_marketing_contact',
            channel: 'web',
            preferredContactChannel: $contact['preferred_contact_channel'],
            preferredContactValue: $contact['preferred_contact_value'],
            visitorContactChannelsJson: $contact['visitor_contact_channels_json'],
            payloadJson: [
                'intent' => $intent,
                'intent_label' => $intentLabel,
            ],
            utmSource: $request->validated('utm_source'),
            utmMedium: $request->validated('utm_medium'),
            utmCampaign: $request->validated('utm_campaign'),
            utmContent: $request->validated('utm_content'),
            utmTerm: $request->validated('utm_term'),
            referrer: $request->headers->get('referer'),
            landingPage: $request->headers->get('referer'),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $createCrmRequest->handle(PublicInboundContext::platform(), $submission);

        $q = platform_marketing_tracking_query();
        if (Route::has('platform.contact')) {
            $target = route('platform.contact', $q);
        } else {
            $target = url('/contact');
            if ($q !== []) {
                $target .= (str_contains($target, '?') ? '&' : '?').http_build_query($q);
            }
        }

        return redirect()
            ->to($target)
            ->with('platform_contact_sent', true)
            ->with('platform_contact_intent', $intent);
    }
}
