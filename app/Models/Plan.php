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

        return in_array($feature, $features);
    }

    public function getLimit(string $key): ?int
    {
        $limits = $this->limits_json ?? [];

        return $limits[$key] ?? null;
    }
}
