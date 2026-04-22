<?php

namespace App\Jobs;

use App\Models\CrmRequest;
use App\Product\CRM\Notifications\PlatformContactTelegramMessage;
use App\Services\Notifications\TelegramTextSender;
use App\Services\Platform\PlatformNotificationSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPlatformContactTelegramNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $crmRequestId,
        public string $chatId,
    ) {
        $this->tries = max(1, (int) config('notification_center.platform_inbound.job_tries', 3));
        $this->onQueue((string) config('notification_center.platform_inbound.queue', 'notifications'));
    }

    public function handle(
        TelegramTextSender $telegramText,
        PlatformNotificationSettings $settings,
    ): void {
        if (! $settings->isChannelEnabled('telegram')) {
            return;
        }

        $token = $settings->telegramBotTokenDecrypted();
        if ($token === null) {
            return;
        }

        $crm = CrmRequest::query()->find($this->crmRequestId);
        if ($crm === null) {
            return;
        }

        if ($crm->request_type !== 'platform_contact' || $crm->tenant_id !== null) {
            return;
        }

        $text = PlatformContactTelegramMessage::build($crm);
        $telegramText->sendPlainText($token, $this->chatId, $text);
    }
}
