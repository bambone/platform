<?php

namespace App\Product\CRM\Notifications;

use App\Models\CrmRequest;
use App\NotificationCenter\NotificationEventRecorder;

/**
 * Platform-scoped staff notification channel for inbound CRM from the marketing site.
 *
 * Intentionally separate from {@see NotificationEventRecorder}: notification_events
 * require a non-null tenant_id (FK). New channels (e.g. VK) register in the container tag
 * `platform_inbound_notification_channels` — do not add channel-specific logic to the action.
 */
interface PlatformInboundNotificationChannel
{
    /**
     * Queue outbound work for this channel (e.g. dispatch jobs). Returns the number of jobs queued.
     */
    public function queueForPlatformContact(CrmRequest $crmRequest): int;
}
