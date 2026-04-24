<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * DB-first curated media catalog (Black Duck and future tenants).
 *
 * Source-of-truth for catalog metadata (role/tags/caption/etc). Binary files live in tenant public storage.
 */
final class TenantMediaAsset extends Model
{
    protected $table = 'tenant_media_assets';

    protected $fillable = [
        'tenant_id',
        'catalog_key',
        'role',
        'logical_path',
        'poster_logical_path',
        'service_slug',
        'page_slug',
        'before_after_group',
        'works_group',
        'sort_order',
        'is_featured',
        'title',
        'caption',
        'summary',
        'alt',
        'service_label',
        'tags_json',
        'aspect_hint',
        'display_variant',
        'badge',
        'cta_label',
        'kind',
        'source_ref',
        'show_on_home',
        'show_on_works',
        'show_on_service',
        'derivatives_json',
    ];

    protected $casts = [
        'tags_json' => 'array',
        'derivatives_json' => 'array',
        'is_featured' => 'boolean',
        // show_on_* сознательно без boolean-cast: nullable в БД → null в PHP (boolean cast давал false и ломал «дефолт роли»).
        'tenant_id' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $m): void {
            $m->catalog_key = self::catalogKeyFor(
                (string) ($m->role ?? ''),
                (string) ($m->logical_path ?? ''),
                (string) ($m->poster_logical_path ?? ''),
                (string) ($m->service_slug ?? ''),
                (string) ($m->page_slug ?? ''),
                (string) ($m->before_after_group ?? ''),
                (string) ($m->works_group ?? ''),
            );
        });
    }

    public static function catalogKeyFor(
        string $role,
        string $logicalPath,
        string $posterLogicalPath = '',
        string $serviceSlug = '',
        string $pageSlug = '',
        string $beforeAfterGroup = '',
        string $worksGroup = '',
    ): string {
        $parts = [
            trim($role),
            trim($logicalPath),
            trim($posterLogicalPath),
            trim($serviceSlug),
            trim($pageSlug),
            trim($beforeAfterGroup),
            trim($worksGroup),
        ];

        return substr(sha1(implode('|', $parts)), 0, 40);
    }

    public function scopeForTenant(Builder $q, int $tenantId): Builder
    {
        return $q->where('tenant_id', $tenantId);
    }
}

