<?php

namespace App\NotificationCenter;

use App\NotificationCenter\Contracts\NotificationChannelDriver;
use App\NotificationCenter\Drivers\EmailNotificationDriver;
use App\NotificationCenter\Drivers\InAppNotificationDriver;
use App\NotificationCenter\Drivers\NullNotificationDriver;
use App\NotificationCenter\Drivers\TelegramNotificationDriver;
use App\NotificationCenter\Drivers\WebhookNotificationDriver;
use App\NotificationCenter\Drivers\OneSignalWebPushDriver;
use App\NotificationCenter\Drivers\WebPushNotificationDriver;
use App\Services\Platform\PlatformNotificationSettings;
use Illuminate\Contracts\Container\Container;

final class NotificationChannelDriverFactory
{
    public function __construct(
        private readonly Container $container,
        private readonly InAppNotificationDriver $inApp,
        private readonly PlatformNotificationSettings $platform,
    ) {}

    public function forType(string $channelType): NotificationChannelDriver
    {
        $type = NotificationChannelType::tryFrom($channelType);

        return match ($type) {
            NotificationChannelType::InApp => $this->inApp,
            NotificationChannelType::Email => $this->platform->isChannelEnabled('email')
                ? $this->container->make(EmailNotificationDriver::class)
                : new NullNotificationDriver('Email channel disabled by platform'),
            NotificationChannelType::Telegram => $this->platform->isChannelEnabled('telegram')
                ? $this->container->make(TelegramNotificationDriver::class)
                : new NullNotificationDriver('Telegram channel disabled by platform'),
            NotificationChannelType::Webhook => $this->platform->isChannelEnabled('webhook')
                ? $this->container->make(WebhookNotificationDriver::class)
                : new NullNotificationDriver('Webhook channel disabled by platform'),
            NotificationChannelType::WebPush => $this->platform->isChannelEnabled('web_push')
                ? $this->container->make(WebPushNotificationDriver::class)
                : new NullNotificationDriver('Web Push channel disabled by platform'),
            NotificationChannelType::WebPushOnesignal => $this->platform->isChannelEnabled('web_push_onesignal')
                ? $this->container->make(OneSignalWebPushDriver::class)
                : new NullNotificationDriver('OneSignal Web Push channel disabled by platform'),
            default => new NullNotificationDriver('Unknown notification channel: '.$channelType),
        };
    }
}
