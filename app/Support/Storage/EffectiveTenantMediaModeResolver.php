<?php

namespace App\Support\Storage;

use App\Models\PlatformSetting;
use App\Models\Tenant;
use Illuminate\Database\QueryException;

final class EffectiveTenantMediaModeResolver
{
    public function effectiveWriteMode(?Tenant $tenant): MediaWriteMode
    {
        if ($tenant !== null) {
            $o = MediaWriteMode::tryFromString($tenant->media_write_mode_override);
            if ($o !== null) {
                return $o;
            }
        }

        $fromSetting = MediaWriteMode::tryFromString(
            $this->platformSetting('media.write_mode_default', '')
        );
        if ($fromSetting !== null) {
            return $fromSetting;
        }

        $fromEnv = MediaWriteMode::tryFromString(config('tenant_storage.media_write_mode_default'));
        if ($fromEnv !== null) {
            return $fromEnv;
        }

        return MediaWriteMode::Dual;
    }

    public function effectiveDeliveryMode(?Tenant $tenant): MediaDeliveryMode
    {
        if ($tenant !== null) {
            $o = MediaDeliveryMode::tryFromString($tenant->media_delivery_mode_override);
            if ($o !== null) {
                return $o;
            }
        }

        $fromSetting = MediaDeliveryMode::tryFromString(
            $this->platformSetting('media.delivery_mode_default', '')
        );
        if ($fromSetting !== null) {
            return $fromSetting;
        }

        $fromEnv = MediaDeliveryMode::tryFromString(config('tenant_storage.media_delivery_mode_default'));
        if ($fromEnv !== null) {
            return $fromEnv;
        }

        return MediaDeliveryMode::R2;
    }

    /**
     * Base path for first-party URLs, e.g. "/media". Leading slash, no trailing slash.
     *
     * @return non-empty-string
     */
    public function localPublicBasePath(): string
    {
        $p = trim($this->platformSetting('media.local_public_base_path', ''));
        if ($p === '') {
            $p = trim((string) config('tenant_storage.media_local_public_base_path', '/media'));
        }
        $p = '/'.ltrim($p, '/');
        $p = rtrim($p, '/') ?: '/media';

        return $p;
    }

    /**
     * Optional override of R2/CDN public base (no trailing slash). Empty = use disk url + config.
     */
    public function r2PublicBaseUrl(): string
    {
        $u = trim($this->platformSetting('media.r2_public_base_url', ''));
        if ($u !== '') {
            return rtrim($u, '/');
        }

        return rtrim((string) config('tenant_storage.media_r2_public_base_url', ''), '/');
    }

    private function platformSetting(string $key, string $default): string
    {
        try {
            return (string) PlatformSetting::get($key, $default);
        } catch (QueryException) {
            return $default;
        }
    }
}
