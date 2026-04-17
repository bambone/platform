<?php

declare(strict_types=1);

namespace App\TenantPush;

use Illuminate\Http\Request;

/**
 * Derives iOS readiness for admin UI. Standalone is detected via short-lived cookie set by JS.
 */
final class TenantPushIosReadinessResolver
{
    private const STANDALONE_COOKIE = 'rb_ios_standalone';

    public function stateForRequest(?Request $request = null): TenantPushIosReadinessState
    {
        $request ??= request();
        $ua = strtolower((string) $request->userAgent());

        if ($ua === '' || ! $this->isIosLike($ua)) {
            return TenantPushIosReadinessState::NotApplicable;
        }

        if ($request->cookie(self::STANDALONE_COOKIE) === '1') {
            return TenantPushIosReadinessState::IosInstalledReadyForPrompt;
        }

        $major = $this->iosMajorMinor($ua);
        if ($major === null) {
            return TenantPushIosReadinessState::IosReadyButNotInstalled;
        }

        if ($major[0] < 16 || ($major[0] === 16 && $major[1] < 4)) {
            return TenantPushIosReadinessState::IosNotSupported;
        }

        return TenantPushIosReadinessState::IosReadyButNotInstalled;
    }

    private function isIosLike(string $ua): bool
    {
        return str_contains($ua, 'iphone')
            || str_contains($ua, 'ipad')
            || str_contains($ua, 'ipod')
            || (str_contains($ua, 'macintosh') && str_contains($ua, 'mobile'));
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function iosMajorMinor(string $ua): ?array
    {
        if (preg_match('/os (\d+)[._](\d+)/', $ua, $m) === 1) {
            return [(int) $m[1], (int) $m[2]];
        }

        return null;
    }
}
