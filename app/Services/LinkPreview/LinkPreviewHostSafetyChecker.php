<?php

declare(strict_types=1);

namespace App\Services\LinkPreview;

/**
 * SSRF-защита: хост не должен резолвиться в loopback/private/link-local.
 */
final class LinkPreviewHostSafetyChecker
{
    public const ERROR_BLOCKED_HOST = 'blocked_host';

    public const ERROR_DNS = 'dns_failed';

    /**
     * Локальные имена без внешнего DNS.
     *
     * @var list<string>
     */
    private const BLOCKED_HOSTNAMES = [
        'localhost',
        'localhost.localdomain',
        'metadata.google.internal',
        'metadata.goog',
    ];

    public static function assertResolvableHostIsPublic(string $host): void
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            throw new LinkPreviewUnsafeHostException(self::ERROR_BLOCKED_HOST, 'Empty host.');
        }

        foreach (self::BLOCKED_HOSTNAMES as $b) {
            if ($host === $b || str_ends_with($host, '.'.$b)) {
                throw new LinkPreviewUnsafeHostException(self::ERROR_BLOCKED_HOST, 'Hostname not allowed.');
            }
        }

        // Уже IP?
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! self::isPublicIp($host)) {
                throw new LinkPreviewUnsafeHostException(self::ERROR_BLOCKED_HOST, 'IP not public.');
            }

            return;
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if ($records === false) {
            $records = [];
        }
        if ($records === []) {
            $legacy = @gethostbynamel($host);
            if (is_array($legacy) && $legacy !== []) {
                foreach ($legacy as $ip) {
                    if (is_string($ip) && $ip !== '' && ! self::isPublicIp($ip)) {
                        throw new LinkPreviewUnsafeHostException(self::ERROR_BLOCKED_HOST, 'Resolved IP not public: '.$ip);
                    }
                }

                return;
            }
            throw new LinkPreviewUnsafeHostException(self::ERROR_DNS, 'Could not resolve host.');
        }

        foreach ($records as $rec) {
            $ip = $rec['ip'] ?? $rec['ipv6'] ?? null;
            if (! is_string($ip) || $ip === '') {
                continue;
            }
            if (! self::isPublicIp($ip)) {
                throw new LinkPreviewUnsafeHostException(self::ERROR_BLOCKED_HOST, 'Resolved IP not public: '.$ip);
            }
        }
    }

    public static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($ip === '::1') {
                return false;
            }
            $bin = @inet_pton($ip);
            if ($bin === false) {
                return false;
            }
            // Unique local fc00::/7
            $b0 = ord($bin[0]);
            if (($b0 & 0xFE) === 0xFC) {
                return false;
            }
            // Link-local fe80::/10
            if ($b0 === 0xFE && (ord($bin[1]) & 0xC0) === 0x80) {
                return false;
            }
            // Loopback ::1 уже исключён
            if ($b0 === 0x00 && $bin === str_repeat("\x00", 15)."\x01") {
                return false;
            }

            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return false;
    }
}
