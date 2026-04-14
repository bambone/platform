<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUserNotificationUiPreference extends Model
{
    protected $table = 'tenant_user_notification_ui_preferences';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'browser_notifications_enabled',
        'sound_enabled',
        'last_permission_state',
    ];

    protected function casts(): array
    {
        return [
            'browser_notifications_enabled' => 'boolean',
            'sound_enabled' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
