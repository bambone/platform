<?php

namespace App\Tenant\StorageQuota;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Realtime usage for the primary Media row (original file). Spatie conversions / derived files are eventually consistent (nightly scan).
 */
final class TenantMediaStorageQuotaObserver
{
    public static function shouldApply(Media $media): bool
    {
        if ($media->disk !== TenantStorageDisks::publicDiskName()) {
            return false;
        }
        $tenantId = self::tenantId($media);
        if ($tenantId === null) {
            return false;
        }
        $rel = $media->getPathRelativeToRoot();

        return str_starts_with($rel, 'tenants/'.$tenantId.'/public/');
    }

    public static function tenantId(Media $media): ?int
    {
        $media->loadMissing('model');
        $model = $media->model;
        if (! $model instanceof Model) {
            return null;
        }
        if (! in_array(BelongsToTenant::class, class_uses_recursive($model), true)) {
            return null;
        }
        $tid = $model->getAttribute('tenant_id');
        if ($tid === null || $tid === '') {
            return null;
        }

        return (int) $tid;
    }

    public function created(Media $media): void
    {
        if (! self::shouldApply($media)) {
            return;
        }
        $tenant = $this->resolveTenant($media);
        if ($tenant === null) {
            return;
        }
        app(TenantStorageQuotaService::class)->increaseUsage($tenant, max(0, (int) $media->size));
    }

    public function updated(Media $media): void
    {
        if (! self::shouldApply($media)) {
            return;
        }
        $tenant = $this->resolveTenant($media);
        if ($tenant === null) {
            return;
        }
        $old = (int) $media->getOriginal('size');
        $new = (int) $media->size;
        $delta = $new - $old;
        if ($delta !== 0) {
            app(TenantStorageQuotaService::class)->applyUsageDelta($tenant, $delta);
        }
    }

    public function deleted(Media $media): void
    {
        if (! self::shouldApply($media)) {
            return;
        }
        $tenant = $this->resolveTenant($media);
        if ($tenant === null) {
            return;
        }
        app(TenantStorageQuotaService::class)->decreaseUsage($tenant, max(0, (int) $media->size));
    }

    private function resolveTenant(Media $media): ?Tenant
    {
        $id = self::tenantId($media);
        if ($id === null) {
            return null;
        }

        return Tenant::query()->find($id);
    }
}
