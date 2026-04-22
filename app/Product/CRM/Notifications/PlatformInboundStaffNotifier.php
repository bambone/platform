<?php

namespace App\Product\CRM\Notifications;

use App\Models\CrmRequest;
use App\Models\CrmRequestActivity;

/**
 * Orchestrates platform staff notifications after a platform-scoped CRM request is committed.
 */
final class PlatformInboundStaffNotifier
{
    /**
     * @param  iterable<PlatformInboundNotificationChannel>  $channels
     */
    public function __construct(
        private readonly iterable $channels,
    ) {}

    public function queueForPlatformContact(CrmRequest $crmRequest): void
    {
        if ($crmRequest->request_type !== 'platform_contact' || $crmRequest->tenant_id !== null) {
            return;
        }

        $telegramJobsQueued = 0;

        foreach ($this->channels as $channel) {
            $queued = $channel->queueForPlatformContact($crmRequest);
            if ($channel instanceof TelegramPlatformInboundNotificationChannel) {
                $telegramJobsQueued += $queued;
            }
        }

        if ($telegramJobsQueued > 0) {
            CrmRequestActivity::query()->create([
                'crm_request_id' => $crmRequest->id,
                'type' => CrmRequestActivity::TYPE_TELEGRAM_QUEUED,
                'meta' => [
                    'chat_ids_count' => $telegramJobsQueued,
                    'channel' => 'telegram',
                ],
                'actor_user_id' => null,
            ]);
        }
    }
}
