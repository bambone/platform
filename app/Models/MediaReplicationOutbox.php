<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaReplicationOutbox extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_FAILED = 'failed';

    public const STATUS_COMPLETED = 'completed';

    public const OPERATION_PUT = 'put';

    public const OPERATION_DELETE = 'delete';

    protected $table = 'media_replication_outbox';

    protected $fillable = [
        'operation',
        'object_key',
        'tenant_id',
        'status',
        'attempts',
        'last_error',
        'available_at',
        'payload_json',
    ];

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
            'payload_json' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
