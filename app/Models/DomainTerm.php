<?php

namespace App\Models;

use App\Terminology\TenantTerminologyService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DomainTerm extends Model
{
    protected $fillable = [
        'term_key',
        'group',
        'default_label',
        'description',
        'value_type',
        'is_required',
        'is_active',
        'is_editable_by_tenant',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'is_editable_by_tenant' => 'boolean',
        ];
    }

    public function presetTerms(): HasMany
    {
        return $this->hasMany(DomainLocalizationPresetTerm::class, 'term_id');
    }

    public function tenantOverrides(): HasMany
    {
        return $this->hasMany(TenantTermOverride::class, 'term_id');
    }

    protected static function booted(): void
    {
        static::saved(function (DomainTerm $term): void {
            if ($term->wasChanged(['default_label', 'is_active', 'is_editable_by_tenant'])) {
                app(TenantTerminologyService::class)->forgetAllTenants();
            }
        });
    }
}
