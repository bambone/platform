<?php

namespace Tests\Unit\Notifications;

use App\Models\NotificationEvent;
use App\Models\NotificationSubscription;
use App\NotificationCenter\NotificationPayloadDto;
use App\NotificationCenter\NotificationSubscriptionConditionEvaluator;
use PHPUnit\Framework\TestCase;

class NotificationSubscriptionConditionEvaluatorTest extends TestCase
{
    public function test_empty_conditions_always_matches(): void
    {
        $eval = new NotificationSubscriptionConditionEvaluator;
        $sub = new NotificationSubscription([
            'conditions_json' => null,
        ]);
        $event = $this->makeEvent(['source' => 'x']);

        $this->assertTrue($eval->matches($sub, $event));
    }

    public function test_meta_all_keys_must_match(): void
    {
        $eval = new NotificationSubscriptionConditionEvaluator;
        $sub = new NotificationSubscription([
            'conditions_json' => ['meta' => ['source' => 'a', 'request_type' => 'b']],
        ]);
        $ok = $this->makeEvent(['source' => 'a', 'request_type' => 'b']);
        $bad = $this->makeEvent(['source' => 'a', 'request_type' => 'c']);

        $this->assertTrue($eval->matches($sub, $ok));
        $this->assertFalse($eval->matches($sub, $bad));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function makeEvent(array $meta): NotificationEvent
    {
        $dto = NotificationPayloadDto::fromValidatedArray([
            'title' => 't',
            'body' => 'b',
            'meta' => $meta,
        ]);
        $event = new NotificationEvent;
        $event->setAttribute('payload_json', $dto->toArray());

        return $event;
    }
}
