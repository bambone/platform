<?php

namespace App\Policies;

use App\Auth\AccessRoles;
use App\Auth\TenantMembershipRoleHierarchy;
use App\Models\User;
use Filament\Facades\Filament;

class UserPolicy
{
    /**
     * В консоли платформы Spatie не обязан выдавать manage_users владельцу/админу платформы;
     * доступ к User в Filament для platform_* задаём явно.
     * В кабинете клиента (admin) по-прежнему действует Gate::before и pivot + manage_users.
     */
    public function viewAny(User $user): bool
    {
        return $this->canManageUsersInCurrentContext($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->canManageUsersInCurrentContext($user);
    }

    public function create(User $user): bool
    {
        if (Filament::getCurrentPanel()?->getId() === 'platform' && $user->hasAnyRole(AccessRoles::platformRoles())) {
            return true;
        }

        if (! $user->can('manage_users')) {
            return false;
        }

        if (Filament::getCurrentPanel()?->getId() === 'admin') {
            $role = $this->tenantPivotRoleForCurrentTenant($user);

            return $role !== null && TenantMembershipRoleHierarchy::canCreateTeamMembers($role);
        }

        return true;
    }

    public function update(User $user, User $model): bool
    {
        if (Filament::getCurrentPanel()?->getId() === 'platform' && $user->hasAnyRole(AccessRoles::platformRoles())) {
            return true;
        }

        if (! $user->can('manage_users')) {
            return false;
        }

        if (Filament::getCurrentPanel()?->getId() === 'admin') {
            $tenant = currentTenant();
            if ($tenant === null) {
                return false;
            }

            return TenantMembershipRoleHierarchy::canEditTeamMember($user, $model, (int) $tenant->id);
        }

        return true;
    }

    public function delete(User $user, User $model): bool
    {
        if (Filament::getCurrentPanel()?->getId() === 'platform' && $user->hasAnyRole(AccessRoles::platformRoles())) {
            return true;
        }

        if (! $user->can('manage_users')) {
            return false;
        }

        if (Filament::getCurrentPanel()?->getId() === 'admin') {
            $tenant = currentTenant();
            if ($tenant === null) {
                return false;
            }

            return TenantMembershipRoleHierarchy::canEditTeamMember($user, $model, (int) $tenant->id);
        }

        return true;
    }

    public function restore(User $user, User $model): bool
    {
        return $this->canManageUsersInCurrentContext($user);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $this->canManageUsersInCurrentContext($user);
    }

    private function canManageUsersInCurrentContext(User $user): bool
    {
        if (Filament::getCurrentPanel()?->getId() === 'platform' && $user->hasAnyRole(AccessRoles::platformRoles())) {
            return true;
        }

        return $user->can('manage_users');
    }

    private function tenantPivotRoleForCurrentTenant(User $user): ?string
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return null;
        }

        $role = $user->tenants()->where('tenant_id', $tenant->id)->first()?->pivot->role;

        return is_string($role) ? $role : null;
    }
}
