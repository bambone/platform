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
    | never full URLs or absolute filesystem paths. RichEditor вложения страниц: …/public/site/page-content/.
    | See docs/operations/r2-tenant-storage.md
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

    /*
    |--------------------------------------------------------------------------
    | Optional cache-buster for TenantStorage::publicUrl()
    |--------------------------------------------------------------------------
    |
    | Query string without leading "?" (e.g. v=20260411). Appended to CDN/disk URLs so
    | browsers and Cloudflare fetch a new object after PutObject replaced the same key
    | (objects often ship Cache-Control: immutable).
    |
    */
    'public_url_version' => env('TENANT_STORAGE_PUBLIC_URL_VERSION', ''),

    /*
    |--------------------------------------------------------------------------
    | Cache-Control on new objects (S3 / R2 public disk only)
    |--------------------------------------------------------------------------
    |
    | Sent on PutObject so browsers and Cloudflare can cache immutable tenant assets.
    | Empty string disables (not recommended for production CDN). Existing objects keep
    | old metadata until re-uploaded or fixed in bucket/CF.
    |
    */
    'public_object_cache_control' => env('TENANT_STORAGE_PUBLIC_OBJECT_CACHE_CONTROL', 'public, max-age=31536000, immutable'),

    /*
    |--------------------------------------------------------------------------
    | Cache-Control for HTTP 302 from legacy /storage/tenants/.../public/... route
    |--------------------------------------------------------------------------
    |
    | Lets browsers reuse the redirect to the CDN URL without hitting Laravel every time.
    | Empty string omits the header.
    |
    */
    'public_storage_redirect_cache_control' => env('TENANT_STORAGE_PUBLIC_REDIRECT_CACHE_CONTROL', 'public, max-age=86400'),

    /*
    |--------------------------------------------------------------------------
    | Stream tenant public files through PHP (cloud disk only)
    |--------------------------------------------------------------------------
    |
    | When false (default), GET /storage/tenants/{id}/public/... on a non-local public disk responds
    | with HTTP 302 to the canonical object URL (CDN/R2). Enable only if you must fix wrong Content-Type
    | on objects without re-uploading (legacy); normal production should keep this false.
    |
    */
    'stream_public_through_origin' => (bool) env('TENANT_STORAGE_STREAM_PUBLIC_THROUGH_ORIGIN', false),

    /*
    |--------------------------------------------------------------------------
    | Local mirror disk (dual write / local delivery)
    |--------------------------------------------------------------------------
    |
    | Object keys match R2 1:1. See docs/operations/tenant-media-local-mirror.md
    |
    */
    'public_mirror_disk' => env('TENANT_STORAGE_PUBLIC_MIRROR_DISK', 'tenant-public-mirror'),

    /*
    | R2 replica target for dual write (must match filesystems disk name).
    |
    */
    'replica_public_disk' => env('TENANT_STORAGE_REPLICA_PUBLIC_DISK', 'r2-public'),

    /*
    | Defaults when platform_settings keys are empty. Tenant columns override per client.
    |
    */
    'media_write_mode_default' => env('TENANT_MEDIA_WRITE_MODE_DEFAULT', 'dual'),

    'media_delivery_mode_default' => env('TENANT_MEDIA_DELIVERY_MODE_DEFAULT', 'r2'),

    /*
    | First-party URL prefix for delivery=local (leading slash, no trailing slash).
    |
    */
    'media_local_public_base_path' => env('TENANT_MEDIA_LOCAL_PUBLIC_BASE_PATH', '/media'),

    /*
    | Optional override for public object URLs when delivery=r2 (no trailing slash).
    | Empty: TenantStorage::publicUrl uses disk url + TENANT_STORAGE_PUBLIC_CDN_URL logic.
    |
    */
    'media_r2_public_base_url' => env('TENANT_MEDIA_R2_PUBLIC_BASE_URL', ''),

];
