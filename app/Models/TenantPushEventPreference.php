<?php

namespace App\Models;

use App\TenantPush\TenantPushRecipientScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPushEventPreference extends Model
{
    protected $table = 'tenant_push_event_preferences';

    protected $fillable = [
        'tenant_id',
        'event_key',
        'is_enabled',
        'delivery_mode',
        'recipient_scope',
        'selected_user_ids_json',
        'quiet_hours_json',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'selected_user_ids_json' => 'array',
            'quiet_hours_json' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return list<int>
     */
    public function selectedUserIds(): array
    {
        $raw = $this->selected_user_ids_json ?? [];

        return array_values(array_unique(array_map('intval', $raw)));
    }

    public function recipientScopeEnum(): TenantPushRecipientScope
    {
        return TenantPushRecipientScope::tryFrom((string) $this->recipient_scope)
            ?? TenantPushRecipientScope::OwnerOnly;
    }
}
