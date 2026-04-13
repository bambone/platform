<?php

namespace App\Support\Storage;

/**
 * Read-only existence checks for diagnostics, verify commands, admin — not for public request hot path.
 */
final class TenantMediaStorageProbe
{
    public function existsLocal(string $objectKey): bool
    {
        try {
            $key = TenantPublicObjectKey::normalize($objectKey);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return TenantStorageDisks::publicMirrorDisk()->exists($key);
    }

    public function existsR2(string $objectKey): bool
    {
        try {
            $key = TenantPublicObjectKey::normalize($objectKey);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return TenantStorageDisks::replicaPublicDisk()->exists($key);
    }
}
