<?php

namespace App\NotificationCenter;

use App\Models\NotificationEvent;
use App\Models\NotificationSubscription;

/**
 * Routes a persisted {@see NotificationEvent} to matching subscriptions.
 *
 * For the same event, subscriptions with the exact {@see NotificationSubscription::$event_key} are processed
 * before {@see NotificationEventRegistry::WILDCARD_EVENT_KEY} so the dedupe by {@see \App\Models\NotificationDelivery::$destination_id}
 * favours the more specific rule when both target the same destination.
 *
 * Personal subscriptions ({@see NotificationSubscription} with non-null {@see NotificationSubscription::$user_id})
 * match only when {@see NotificationRoutingContext::$recipientUserIds} is non-null and non-empty and includes that user.
 * Without an explicit recipient list, only shared subscriptions (user_id null) are considered — by design (MVP).
 */
final class NotificationRouter
{
    public function __construct(
        private readonly NotificationDeliveryPlanner $planner,
        private readonly NotificationSubscriptionConditionEvaluator $conditionEvaluator,
    ) {}

    /**
     * @return list<int> delivery ids
     */
    public function routeEvent(NotificationEvent $event, ?NotificationRoutingContext $context = null): array
    {
        $context ??= new NotificationRoutingContext;

        $eventSeverity = NotificationSeverity::tryFromString($event->severity) ?? NotificationSeverity::Normal;

        $wildcard = NotificationEventRegistry::WILDCARD_EVENT_KEY;

        $subs = NotificationSubscription::query()
            ->where('tenant_id', $event->tenant_id)
            ->where(function ($q) use ($event, $wildcard): void {
                $q->where('event_key', $event->event_key)
                    ->orWhere('event_key', $wildcard);
            })
            ->where('enabled', true)
            ->with('destinations')
            ->where(function ($q) use ($context): void {
                $q->whereNull('user_id');
                if ($context->recipientUserIds !== null && $context->recipientUserIds !== []) {
                    $q->orWhereIn('user_id', $context->recipientUserIds);
                }
            })
            // Точное совпадение события — раньше wildcard, чтобы дедуп по destination применял «более специфичное» правило первым.
            ->orderByRaw('CASE WHEN event_key = ? THEN 0 WHEN event_key = ? THEN 1 ELSE 2 END', [$event->event_key, $wildcard])
            ->orderBy('id')
            ->get();

        $deliveryIds = [];
        $blockedDestinationIds = [];

        foreach ($subs as $subscription) {
            if (! $this->conditionEvaluator->matches($subscription, $event)) {
                continue;
            }

            if (! $this->passesSeverityMin($subscription, $eventSeverity)) {
                continue;
            }

            $deliveries = $this->planner->planFromSubscription($event, $subscription, $blockedDestinationIds);

            foreach ($deliveries as $d) {
                $deliveryIds[] = $d->id;
                $blockedDestinationIds[] = (int) $d->destination_id;
            }
        }

        return $deliveryIds;
    }

    private function passesSeverityMin(NotificationSubscription $subscription, NotificationSeverity $eventSeverity): bool
    {
        $min = NotificationSeverity::tryFromString($subscription->severity_min);
        if ($min === null) {
            return true;
        }

        return $eventSeverity->isAtLeast($min);
    }
}
