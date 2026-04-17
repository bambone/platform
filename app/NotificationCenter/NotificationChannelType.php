<?php

namespace App\NotificationCenter;

enum NotificationChannelType: string
{
    case Email = 'email';
    case Telegram = 'telegram';
    case WebPush = 'web_push';
    case WebPushOnesignal = 'web_push_onesignal';
    case Webhook = 'webhook';
    case Sms = 'sms';
    case Vk = 'vk';
    case InApp = 'in_app';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Telegram => 'Telegram',
            self::WebPush => 'Web Push',
            self::WebPushOnesignal => 'OneSignal Web Push',
            self::Webhook => 'Webhook',
            self::Sms => 'SMS',
            self::Vk => 'VK',
            self::InApp => 'В кабинете',
        };
    }
}
