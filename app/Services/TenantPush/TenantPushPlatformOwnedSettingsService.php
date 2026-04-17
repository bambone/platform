<?php

declare(strict_types=1);

namespace App\Services\TenantPush;

use App\Models\Tenant;
use App\Models\User;
use App\TenantPush\TenantPushFeatureGate;
use App\TenantPush\TenantPushOverride;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Запись platform-owned полей tenant_push_settings с аудитом (платформа и программные вызовы).
 */
final class TenantPushPlatformOwnedSettingsService
{
    public function __construct(
        private readonly TenantPushFeatureGate $featureGate,
        private readonly TenantPushPlatformAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{platform_push_override?: string, platform_push_commercial_active?: bool, platform_push_self_serve_allowed?: bool}  $form
     */
    public function applyFromFormData(Tenant $tenant, array $form, ?Authenticatable $actor): void
    {
        $override = TenantPushOverride::tryFrom((string) ($form['platform_push_override'] ?? ''))
            ?? TenantPushOverride::InheritPlan;
        $this->applyScalars(
            $tenant,
            $override,
            (bool) ($form['platform_push_commercial_active'] ?? false),
            (bool) ($form['platform_push_self_serve_allowed'] ?? true),
            $actor,
        );
    }

    public function applyScalars(
        Tenant $tenant,
        TenantPushOverride $override,
        bool $commercialActive,
        bool $selfServeAllowed,
        ?Authenticatable $actor,
    ): void {
        $before = $this->featureGate->findSettings($tenant);
        $beforeScalars = [
            'push_override' => $before?->push_override,
            'commercial_service_active' => $before?->commercial_service_active,
            'self_serve_allowed' => $before?->self_serve_allowed,
        ];

        $settings = $this->featureGate->ensureSettings($tenant);
        $settings->push_override = $override->value;
        $settings->commercial_service_active = $commercialActive;
        $settings->self_serve_allowed = $selfServeAllowed;
        $settings->save();

        $this->auditLogger->logIfChanged(
            (int) $tenant->id,
            $actor,
            $beforeScalars,
            [
                'push_override' => $settings->push_override,
                'commercial_service_active' => $settings->commercial_service_active,
                'self_serve_allowed' => $settings->self_serve_allowed,
            ],
        );
    }
}
