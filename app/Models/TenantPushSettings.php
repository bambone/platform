<?php

namespace App\Models;

use App\TenantPush\TenantPushOverride;
use App\TenantPush\TenantPushProviderStatus;
use App\TenantPush\TenantPushSetupStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPushSettings extends Model
{
    protected $table = 'tenant_push_settings';

    protected $fillable = [
        'tenant_id',
        'push_override',
        'self_serve_allowed',
        'commercial_service_active',
        'setup_status',
        'provider_status',
        'canonical_origin',
        'canonical_host',
        'onesignal_app_id',
        'onesignal_app_api_key_encrypted',
        'onesignal_site_name',
        'onesignal_config_verified_at',
        'onesignal_last_verification_error',
        'onesignal_key_pending_verification',
        'test_push_last_sent_at',
        'test_push_last_result_status',
        'test_push_last_result_message',
        'is_push_enabled',
        'is_pwa_enabled',
        'pwa_manifest_status',
        'pwa_worker_status',
        'pwa_name',
        'pwa_short_name',
        'pwa_theme_color',
        'pwa_background_color',
        'pwa_display',
        'pwa_start_url',
        'pwa_icons_json',
    ];

    protected function casts(): array
    {
        return [
            'self_serve_allowed' => 'boolean',
            'commercial_service_active' => 'boolean',
            'onesignal_app_api_key_encrypted' => 'encrypted',
            'onesignal_config_verified_at' => 'datetime',
            'onesignal_key_pending_verification' => 'boolean',
            'test_push_last_sent_at' => 'datetime',
            'is_push_enabled' => 'boolean',
            'is_pwa_enabled' => 'boolean',
            'pwa_icons_json' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pushOverrideEnum(): TenantPushOverride
    {
        return TenantPushOverride::tryFrom((string) $this->push_override) ?? TenantPushOverride::InheritPlan;
    }

    public function providerStatusEnum(): TenantPushProviderStatus
    {
        return TenantPushProviderStatus::tryFrom((string) $this->provider_status) ?? TenantPushProviderStatus::NotConfigured;
    }

    public function setupStatusEnum(): TenantPushSetupStatus
    {
        return TenantPushSetupStatus::tryFrom((string) $this->setup_status) ?? TenantPushSetupStatus::NotStarted;
    }
}
