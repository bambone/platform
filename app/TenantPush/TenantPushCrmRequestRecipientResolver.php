<?php

declare(strict_types=1);

namespace App\TenantPush;

use App\Auth\AccessRoles;
use App\Models\Tenant;
use App\Models\TenantPushEventPreference;
use App\Models\User;

final class TenantPushCrmRequestRecipientResolver
{
    /**
     * @return list<int>
     */
    public function resolveOnesignalRecipientUserIds(Tenant $tenant): array
    {
        $pref = TenantPushEventPreference::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_key', 'crm_request.created')
            ->first();

        if ($pref === null || ! $pref->is_enabled) {
            return [];
        }

        $scope = $pref->recipientScopeEnum();

        return match ($scope) {
            TenantPushRecipientScope::OwnerOnly => $this->ownerUserId($tenant),
            TenantPushRecipientScope::SelectedUsers => $this->filterUserIdsEligibleForTenantPanel($tenant, $pref->selectedUserIds()),
            TenantPushRecipientScope::AllAdmins => $this->allAdminUserIds($tenant),
        };
    }

    /**
     * @param  list<int>  $candidateUserIds
     * @return list<int>
     */
    private function filterUserIdsEligibleForTenantPanel(Tenant $tenant, array $candidateUserIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $candidateUserIds), fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        return $tenant->users()
            ->wherePivot('status', 'active')
            ->wherePivotIn('role', AccessRoles::tenantMembershipRolesForPanel())
            ->whereIn('users.id', $ids)
            ->orderBy('users.id')
            ->pluck('users.id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function ownerUserId(Tenant $tenant): array
    {
        $id = $tenant->owner_user_id;

        return $id !== null ? [(int) $id] : [];
    }

    /**
     * @return list<int>
     */
    private function allAdminUserIds(Tenant $tenant): array
    {
        $ids = [];
        $tenant->users()
            ->wherePivot('status', 'active')
            ->wherePivotIn('role', AccessRoles::tenantMembershipRolesForPanel())
            ->get()
            ->each(function (User $u) use (&$ids): void {
                $ids[] = (int) $u->id;
            });

        return array_values(array_unique($ids));
    }
}
