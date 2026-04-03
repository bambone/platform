<?php

namespace App\Tenant\StorageQuota;

enum TenantStorageQuotaEventType: string
{
    case QuotaChanged = 'quota_changed';
    case UsageWarning20 = 'usage_warning_20';
    case UsageCritical10 = 'usage_critical_10';
    case UsageExceeded = 'usage_exceeded';
    case UsageBackToNormal = 'usage_back_to_normal';
    case UploadBlockedQuotaExceeded = 'upload_blocked_quota_exceeded';
    case Recalculated = 'recalculated';
}
