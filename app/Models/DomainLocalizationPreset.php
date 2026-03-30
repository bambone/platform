<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DomainLocalizationPreset extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function presetTerms(): HasMany
    {
        return $this->hasMany(DomainLocalizationPresetTerm::class, 'preset_id');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'domain_localization_preset_id');
    }
}
