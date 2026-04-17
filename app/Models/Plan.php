<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'limits_json',
        'features_json',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'limits_json' => 'array',
        'features_json' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function hasFeature(string $feature): bool
    {
        $features = $this->features_json ?? [];

        return in_array($feature, $features, true);
    }

    /**
     * Тариф по умолчанию в мастере онбординга.
     *
     * 1) Активный тариф со slug `lite`, иначе
     * 2) первый активный тариф по `sort_order`, `id`, иначе
     * 3) `null` (не подставляем неактивный тариф, даже если он единственная строка в таблице).
     */
    public static function defaultIdForOnboarding(): ?int
    {
        $liteId = static::query()
            ->where('slug', 'lite')
            ->where('is_active', true)
            ->value('id');
        if ($liteId !== null) {
            return (int) $liteId;
        }

        $firstActiveId = static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        return $firstActiveId !== null ? (int) $firstActiveId : null;
    }

    public function getLimit(string $key): ?int
    {
        $limits = $this->limits_json ?? [];

        return $limits[$key] ?? null;
    }
}
