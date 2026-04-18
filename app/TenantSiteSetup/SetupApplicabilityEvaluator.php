<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Auth\TenantPivotPermissions;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class SetupApplicabilityEvaluator
{
    public function __construct(
        private readonly SetupProfileRepository $profiles,
        private readonly TenantOnboardingBranchResolver $branchResolver,
    ) {}

    /**
     * Returns applicability_status string (see blueprint §1.2).
     *
     * @param  ?User  $user  явный пользователь (как в {@see SetupJourneyBuilder}); иначе {@see Auth::user()} для совместимости с фоновыми вызовами
     */
    public function evaluateItem(Tenant $tenant, SetupItemDefinition $def, ?User $user = null): string
    {
        $actor = $user instanceof User ? $user : Auth::user();

        if ($def->themeConstraints !== null && $def->themeConstraints !== []) {
            if (! in_array((string) $tenant->theme_key, $def->themeConstraints, true)) {
                return 'not_applicable_by_system';
            }
        }

        if ($def->featureConstraints !== null) {
            foreach ($def->featureConstraints as $flag => $expected) {
                if ($flag === 'scheduling_module_enabled' && (bool) $tenant->scheduling_module_enabled !== (bool) $expected) {
                    return 'not_applicable_by_system';
                }
                if ($flag === 'suppress_when_effective_crm_only' && $expected === true) {
                    $resolution = $this->branchResolver->resolve(
                        $tenant,
                        $actor,
                        $this->profiles->getMerged((int) $tenant->id),
                    );
                    if ($resolution->effectiveBranchId === TenantOnboardingBranchId::CrmOnly->value) {
                        return 'not_applicable_by_system';
                    }
                }
            }
        }

        if ($def->key === 'setup.booking_notifications_brief') {
            if ($actor instanceof User) {
                $pivotRole = $actor->tenants()->where('tenants.id', $tenant->id)->first()?->pivot?->role;
                if (! is_string($pivotRole)) {
                    return 'not_applicable_by_system';
                }
                $canSurface = ($tenant->scheduling_module_enabled && TenantPivotPermissions::pivotRoleAllows($pivotRole, 'manage_scheduling'))
                    || TenantPivotPermissions::pivotRoleAllows($pivotRole, 'manage_notifications')
                    || TenantPivotPermissions::pivotRoleAllows($pivotRole, 'manage_notification_destinations')
                    || TenantPivotPermissions::pivotRoleAllows($pivotRole, 'manage_notification_subscriptions');
                if (! $canSurface) {
                    return 'not_applicable_by_system';
                }
            }
        }

        $profile = $this->profiles->get((int) $tenant->id);
        foreach ($def->profileDependencyKeys as $pkey) {
            if (array_key_exists($pkey, $profile)) {
                // Reserved: profile can force not_applicable for optional modules later.
            }
        }

        return 'applicable';
    }
}
