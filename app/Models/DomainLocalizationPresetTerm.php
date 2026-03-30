<?php

namespace App\Models;

use App\Terminology\TenantTerminologyService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainLocalizationPresetTerm extends Model
{
    protected $fillable = [
        'preset_id',
        'term_id',
        'label',
        'short_label',
        'notes',
    ];

    public function preset(): BelongsTo
    {
        return $this->belongsTo(DomainLocalizationPreset::class, 'preset_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(DomainTerm::class, 'term_id');
    }

    protected static function booted(): void
    {
        static::saved(function (DomainLocalizationPresetTerm $row): void {
            app(TenantTerminologyService::class)->forgetTenantsUsingPreset($row->preset_id);
        });

        static::deleted(function (DomainLocalizationPresetTerm $row): void {
            app(TenantTerminologyService::class)->forgetTenantsUsingPreset($row->preset_id);
        });
    }
}
