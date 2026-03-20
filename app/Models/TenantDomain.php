<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    protected $fillable = [
        'tenant_id',
        'host',
        'type',
        'is_primary',
        'ssl_status',
        'verification_status',
        'dns_target',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function types(): array
    {
        return [
            'subdomain' => 'Поддомен',
            'custom' => 'Кастомный домен',
        ];
    }
}
