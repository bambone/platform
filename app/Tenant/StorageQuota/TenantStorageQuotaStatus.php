<?php

namespace App\Tenant\StorageQuota;

enum TenantStorageQuotaStatus: string
{
    case Ok = 'ok';
    case Warning20 = 'warning_20';
    case Critical10 = 'critical_10';
    case Exceeded = 'exceeded';
}
