<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Logical disks for TenantStorage (public / private prefixes)
    |--------------------------------------------------------------------------
    |
    | Defaults keep local `public` + private from SEO_FILES_DISK (see config/seo.php).
    | For Cloudflare R2: e.g. TENANT_STORAGE_PUBLIC_DISK=r2-public and either set
    | TENANT_STORAGE_PRIVATE_DISK=r2-private or SEO_FILES_DISK=r2-private.
    |
    | DB invariant: store only object keys relative to disk root (e.g. tenants/1/public/site/...),
    | never full URLs or absolute filesystem paths. See docs/operations/r2-tenant-storage.md
    |
    | Private disk (r2-private): do not expose via TenantStorage::publicUrl or disk->url in UI;
    | use backend / signed URL / temporary URL only.
    |
    */
    'public_disk' => env('TENANT_STORAGE_PUBLIC_DISK', 'public'),

    'private_disk' => env('TENANT_STORAGE_PRIVATE_DISK', env('SEO_FILES_DISK', 'local')),

    /*
    |--------------------------------------------------------------------------
    | Enforce current-tenant context (HTTP tenant sites / tenant Filament)
    |--------------------------------------------------------------------------
    |
    | When true, TenantStorage::for($id) throws if the resolved request tenant
    | differs from $id. Skipped on non-tenant hosts (platform console), in console
    | without CurrentTenant, or when binding is missing — so jobs and artisan
    | commands keep working.
    |
    */
    'enforce_current_tenant_context' => (bool) env('TENANT_STORAGE_ENFORCE_CONTEXT', true),

    /*
    |--------------------------------------------------------------------------
    | Optional CDN / asset host for TenantStorage::publicUrl()
    |--------------------------------------------------------------------------
    |
    | No trailing slash. When empty, the public disk URL (e.g. /storage/...) is used.
    | For S3/CDN, point the public disk to the driver and leave this empty, or set
    | a dedicated CDN origin here if files are synced out-of-band.
    |
    */
    'public_cdn_base_url' => env('TENANT_STORAGE_PUBLIC_CDN_URL', ''),

];
