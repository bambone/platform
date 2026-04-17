<?php

declare(strict_types=1);

namespace App\TenantPush;

/**
 * Maps OneSignal REST responses to {@see TenantPushDiagnosticCode}.
 */
final class TenantPushOnesignalResponseNormalizer
{
    public static function codeForVerify(int $status, mixed $body): TenantPushDiagnosticCode
    {
        if ($status >= 200 && $status < 300) {
            return TenantPushDiagnosticCode::Ok;
        }

        $blob = self::blob($body);

        if ($status === 404) {
            return TenantPushDiagnosticCode::AppNotFoundOrNotAccessible;
        }

        if ($status === 401 || $status === 403) {
            if (self::looksLikeWrongKeyLayer($blob)) {
                return TenantPushDiagnosticCode::WrongKeyType;
            }
            if (self::looksLikeIpRestriction($blob)) {
                return TenantPushDiagnosticCode::IpNotAllowed;
            }

            return TenantPushDiagnosticCode::AppNotFoundOrNotAccessible;
        }

        return TenantPushDiagnosticCode::Unknown;
    }

    /**
     * @param  array{ok: bool, status: int, body: mixed}|array<string, mixed>  $result
     */
    public static function codeForNotificationSend(array $result): TenantPushDiagnosticCode
    {
        if (($result['ok'] ?? false) === true) {
            return TenantPushDiagnosticCode::Ok;
        }

        $status = (int) ($result['status'] ?? 0);
        $body = $result['body'] ?? null;
        $blob = self::blob($body);

        if ($status === 404) {
            return TenantPushDiagnosticCode::AppNotFoundOrNotAccessible;
        }

        if ($status === 400 && self::looksLikeNoRecipients($blob)) {
            return TenantPushDiagnosticCode::NoActiveSubscriptions;
        }

        if ($status === 401 || $status === 403) {
            if (self::looksLikeWrongKeyLayer($blob)) {
                return TenantPushDiagnosticCode::WrongKeyType;
            }
            if (self::looksLikeIpRestriction($blob)) {
                return TenantPushDiagnosticCode::IpNotAllowed;
            }
        }

        return TenantPushDiagnosticCode::Unknown;
    }

    private static function blob(mixed $body): string
    {
        if (is_array($body)) {
            return strtolower(json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        return strtolower((string) $body);
    }

    private static function looksLikeWrongKeyLayer(string $blob): bool
    {
        return str_contains($blob, 'organization')
            || str_contains($blob, 'org-level')
            || str_contains($blob, 'not authorized for app');
    }

    private static function looksLikeIpRestriction(string $blob): bool
    {
        return str_contains($blob, 'ip')
            && (str_contains($blob, 'allow') || str_contains($blob, 'whitelist'));
    }

    private static function looksLikeNoRecipients(string $blob): bool
    {
        return str_contains($blob, 'no subscribers')
            || str_contains($blob, 'no users')
            || str_contains($blob, 'external_user')
            || str_contains($blob, 'player');
    }
}
