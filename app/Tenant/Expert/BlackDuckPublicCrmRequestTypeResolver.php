<?php

declare(strict_types=1);

namespace App\Tenant\Expert;

use App\Models\BookableService;

/**
 * Публичная форма не должна диктовать {@code crm_request_type}: с клиента приходит только
 * {@see \App\Http\Requests\StoreExpertInquiryRequest::inquiry_intent} (whitelist) + контекст;
 * итоговый тип заявки вычисляется здесь.
 */
final class BlackDuckPublicCrmRequestTypeResolver
{
    /**
     * @param  array<string, mixed>  $validated  {@see StoreExpertInquiryRequest::validated()}
     */
    public function resolve(int $tenantId, array $validated): string
    {
        $intent = trim((string) ($validated['inquiry_intent'] ?? ''));
        if ($intent !== '') {
            return match ($intent) {
                'book_slot' => 'booking_request',
                'price_quote' => 'quote_request',
                'callback' => 'callback_request',
                'messenger' => 'messenger_request',
                'question' => 'question_request',
                'service_inquiry' => 'expert_service_inquiry',
                default => 'expert_service_inquiry',
            };
        }

        $slug = trim((string) ($validated['service_slug'] ?? ''));
        if ($slug !== '') {
            $bookable = BookableService::query()
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->first();
            if ($bookable !== null) {
                return $bookable->requires_confirmation
                    ? 'quote_request'
                    : 'booking_request';
            }
        }

        $goal = mb_strtolower((string) ($validated['goal_text'] ?? ''));
        if (str_contains($goal, 'расчёт') || str_contains($goal, 'стоим') || str_contains($goal, 'цен')) {
            return 'quote_request';
        }
        if (str_contains($goal, 'перезвон') || str_contains($goal, 'позвон')) {
            return 'callback_request';
        }

        return 'expert_service_inquiry';
    }
}
