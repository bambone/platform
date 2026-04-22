<?php

namespace App\Rules;

use App\NotificationCenter\NotificationEventRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * @see NotificationEventRegistry::isSubscribableEventKey
 */
final class ValidNotificationSubscriptionEventKey implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (! NotificationEventRegistry::isSubscribableEventKey($value)) {
            $fail('Укажите известный тип события или вариант «Все уведомления».');
        }
    }
}
