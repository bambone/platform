<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'integration_id',
        'action',
        'status',
        'request_data',
        'response_data',
        'error_message',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
