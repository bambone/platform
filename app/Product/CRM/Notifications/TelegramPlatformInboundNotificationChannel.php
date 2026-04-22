<?php

namespace App\Product\CRM\Notifications;

use App\Jobs\SendPlatformContactTelegramNotification;
use App\Models\CrmRequest;
use App\Services\Platform\PlatformNotificationSettings;

final class TelegramPlatformInboundNotificationChannel implements PlatformInboundNotificationChannel
{
    public function __construct(
        private readonly PlatformNotificationSettings $settings,
    ) {}

    public function queueForPlatformContact(CrmRequest $crmRequest): int
    {
        if (! $this->settings->isChannelEnabled('telegram')) {
            return 0;
        }

        if ($this->settings->telegramBotTokenDecrypted() === null) {
            return 0;
        }

        $chatIds = $this->settings->platformContactTelegramChatIds();
        if ($chatIds === []) {
            return 0;
        }

        $count = 0;
        foreach ($chatIds as $chatId) {
            SendPlatformContactTelegramNotification::dispatch($crmRequest->id, $chatId);
            $count++;
        }

        return $count;
    }
}
