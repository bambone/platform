<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRequest;
use App\Jobs\SendLeadTelegramNotification;
use App\Product\CRM\Actions\CreateCrmRequestFromPublicForm;
use App\Product\CRM\DTO\PublicInboundContext;
use App\Product\CRM\DTO\PublicInboundSubmission;
use App\Terminology\DomainTermKeys;
use App\Terminology\TenantTerminologyService;
use Illuminate\Http\JsonResponse;

class LeadController extends Controller
{
    public function store(
        StoreLeadRequest $request,
        CreateCrmRequestFromPublicForm $createCrmRequest,
    ): JsonResponse {
        $tenant = currentTenant();
        abort_if($tenant === null, 404);

        $submission = new PublicInboundSubmission(
            requestType: 'tenant_booking',
            name: $request->validated('name'),
            phone: $request->validated('phone'),
            email: $request->validated('email'),
            message: $request->validated('comment'),
            source: $request->validated('source') ?? 'booking_form',
            channel: 'web',
            payloadJson: [
                'motorcycle_id' => $request->validated('motorcycle_id'),
                'rental_date_from' => $request->validated('rental_date_from'),
                'rental_date_to' => $request->validated('rental_date_to'),
            ],
            landingPage: $request->validated('page_url') ?? $request->header('referer'),
            referrer: $request->header('referer'),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $result = $createCrmRequest->handle(PublicInboundContext::tenant($tenant->id), $submission);

        $lead = $result->lead;
        abort_if($lead === null, 500);

        // Кандидат на консолидацию: уведомление привязано к downstream Lead; почта/CRM-activity уже в product layer.
        SendLeadTelegramNotification::dispatch($lead);

        $leadWord = app(TenantTerminologyService::class)->label($tenant, DomainTermKeys::LEAD);

        return response()->json([
            'success' => true,
            'message' => 'Спасибо! Данные по «'.$leadWord.'» сохранены. Мы скоро свяжемся с вами.',
            'lead_id' => $lead->id,
            'crm_request_id' => $result->crmRequest->id,
        ]);
    }
}
