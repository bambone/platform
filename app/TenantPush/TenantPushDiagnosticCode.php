<?php

declare(strict_types=1);

namespace App\TenantPush;

/**
 * Machine-readable codes for support / UI (plan §7).
 */
enum TenantPushDiagnosticCode: string
{
    case Ok = 'ok';
    case Unknown = 'unknown';

    case WrongKeyType = 'wrong_key_type';
    case IpNotAllowed = 'ip_not_allowed';
    case AppNotFoundOrNotAccessible = 'app_not_found_or_not_accessible';
    case OriginMismatch = 'origin_mismatch';
    case WorkerUnreachable = 'worker_unreachable';
    case ManifestInvalid = 'manifest_invalid';
    case NoActiveSubscriptions = 'no_active_subscriptions';
}
