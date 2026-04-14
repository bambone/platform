<?php

namespace App\NotificationCenter;

use App\Models\NotificationEvent;
use App\Models\NotificationSubscription;

/**
 * Evaluates {@see NotificationSubscription::$conditions_json} against a persisted event payload.
 *
 * Supported shape: `{ "meta": { "source": "...", "request_type": "..." } }` — all specified meta keys must match.
 */
final class NotificationSubscriptionConditionEvaluator
{
    public function matches(NotificationSubscription $subscription, NotificationEvent $event): bool
    {
        $conditions = $subscription->conditions_json;
        if (! is_array($conditions) || $conditions === []) {
            return true;
        }

        $payload = $event->payloadDto();

        if (isset($conditions['meta']) && is_array($conditions['meta'])) {
            foreach ($conditions['meta'] as $key => $expected) {
                $actual = $payload->meta[$key] ?? null;
                if (! $this->valueEquals($actual, $expected)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function valueEquals(mixed $actual, mixed $expected): bool
    {
        if ($expected === null) {
            return $actual === null;
        }

        if (is_bool($expected)) {
            return (bool) $actual === $expected;
        }

        if (is_int($expected) || is_float($expected)) {
            return is_numeric($actual) && (float) $actual === (float) $expected;
        }

        return (string) $actual === (string) $expected;
    }
}
