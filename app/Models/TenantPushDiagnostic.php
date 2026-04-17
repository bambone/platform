<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPushDiagnostic extends Model
{
    protected $table = 'tenant_push_diagnostics';

    protected $fillable = [
        'tenant_id',
        'check_type',
        'status',
        'code',
        'message',
        'details_json',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'details_json' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
