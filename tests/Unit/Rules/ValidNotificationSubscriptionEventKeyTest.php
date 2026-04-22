<?php

namespace Tests\Unit\Rules;

use App\NotificationCenter\NotificationEventRegistry;
use App\Rules\ValidNotificationSubscriptionEventKey;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidNotificationSubscriptionEventKeyTest extends TestCase
{
    public function test_accepts_wildcard_and_known_keys(): void
    {
        $rule = [new ValidNotificationSubscriptionEventKey];

        $v1 = Validator::make(['k' => NotificationEventRegistry::WILDCARD_EVENT_KEY], ['k' => $rule]);
        $this->assertTrue($v1->passes());

        $v2 = Validator::make(['k' => 'crm_request.created'], ['k' => $rule]);
        $this->assertTrue($v2->passes());
    }

    public function test_rejects_unknown_key(): void
    {
        $v = Validator::make(['k' => 'definitely.not.a.key'], ['k' => [new ValidNotificationSubscriptionEventKey]]);

        $this->assertFalse($v->passes());
    }

    /**
     * Контракт с {@see \App\NotificationCenter\NotificationEventRegistry::isSubscribableEventKey}:
     * единый список допустимых ключей (включая wildcard) — см. {@see ValidNotificationSubscriptionEventKey}.
     */
    public function test_wildcard_allowed_iff_registry_marks_subscribable(): void
    {
        $wildcard = NotificationEventRegistry::WILDCARD_EVENT_KEY;
        $this->assertTrue(NotificationEventRegistry::isSubscribableEventKey($wildcard));

        $rule = [new ValidNotificationSubscriptionEventKey];
        $v = Validator::make(['k' => $wildcard], ['k' => $rule]);
        $this->assertTrue($v->passes());
    }
}
