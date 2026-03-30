<?php

namespace App\Models;

use App\Terminology\TenantTerminologyService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantTermOverride extends Model
{
    protected $fillable = [
        'tenant_id',
        'term_id',
        'label',
        'short_label',
        'source',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(DomainTerm::class, 'term_id');
    }

    protected static function booted(): void
    {
        static::saved(function (TenantTermOverride $row): void {
            app(TenantTerminologyService::class)->forgetTenant($row->tenant_id);
        });

        static::deleted(function (TenantTermOverride $row): void {
            app(TenantTerminologyService::class)->forgetTenant($row->tenant_id);
        });
    }
}
