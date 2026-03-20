<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRequest;
use App\Jobs\SendLeadTelegramNotification;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;

class LeadController extends Controller
{
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = Lead::create([
            'name' => $request->validated('name'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'comment' => $request->validated('comment'),
            'motorcycle_id' => $request->validated('motorcycle_id'),
            'rental_date_from' => $request->validated('rental_date_from'),
            'rental_date_to' => $request->validated('rental_date_to'),
            'source' => $request->validated('source') ?? 'booking_form',
            'page_url' => $request->validated('page_url') ?? $request->header('referer'),
            'status' => 'new',
        ]);

        SendLeadTelegramNotification::dispatch($lead);

        return response()->json([
            'success' => true,
            'message' => 'Заявка успешно отправлена! Наш менеджер скоро свяжется с вами.',
            'lead_id' => $lead->id,
        ]);
    }
}
