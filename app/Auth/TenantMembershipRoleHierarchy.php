<?php

namespace App\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Иерархия pivot-ролей tenant_user для UI «Команда» (кабинет клиента).
 * Чем выше rank — тем больше полномочий.
 */
final class TenantMembershipRoleHierarchy
{
    /** @var array<string, int> */
    private const RANK = [
        'tenant_owner' => 60,
        'tenant_admin' => 50,
        'booking_manager' => 40,
        'fleet_manager' => 30,
        'content_manager' => 20,
        'operator' => 10,
    ];

    public static function rank(?string $role): int
    {
        if ($role === null || $role === '') {
            return 0;
        }

        return self::RANK[$role] ?? 0;
    }

    /**
     * Роли, которые можно назначить **новому** участнику (строго ниже роли создателя).
     *
     * @return list<string>
     */
    public static function creatableRoleKeys(string $creatorRole): array
    {
        $r = self::rank($creatorRole);
        if ($r <= 0) {
            return [];
        }

        return array_keys(array_filter(self::RANK, fn (int $rk): bool => $rk < $r));
    }

    public static function canCreateTeamMembers(string $actorRole): bool
    {
        return in_array($actorRole, ['tenant_owner', 'tenant_admin'], true);
    }

    /**
     * Роли, доступные в селекте при редактировании **другого** пользователя (не себя).
     *
     * @return list<string>
     */
    public static function editableRoleKeysForTarget(string $actorRole, string $targetRole): array
    {
        if ($actorRole === 'tenant_owner') {
            return array_keys(self::RANK);
        }

        if ($actorRole === 'tenant_admin') {
            if (self::rank($targetRole) >= self::rank('tenant_admin')) {
                return [];
            }

            return array_keys(array_filter(
                self::RANK,
                fn (int $rk): bool => $rk < self::rank('tenant_admin')
            ));
        }

        return [];
    }

    /**
     * Роли при редактировании **своей** учётки (нельзя повысить выше текущей, напр. admin → owner).
     *
     * @return list<string>
     */
    public static function editableRoleKeysForSelf(string $actorRole): array
    {
        $r = self::rank($actorRole);
        if ($r <= 0) {
            return [];
        }

        return array_keys(array_filter(self::RANK, fn (int $rk): bool => $rk <= $r));
    }

    public static function canEditTeamMember(User $actor, User $target, int $tenantId): bool
    {
        $actorRole = $actor->tenants()->where('tenant_id', $tenantId)->first()?->pivot->role;
        $targetRole = $target->tenants()->where('tenant_id', $tenantId)->first()?->pivot->role;
        if ($actorRole === null || $targetRole === null) {
            return false;
        }

        if ($actor->id === $target->id) {
            return true;
        }

        if ($actorRole === 'tenant_owner') {
            return true;
        }

        if ($actorRole === 'tenant_admin') {
            return self::rank($targetRole) < self::rank('tenant_admin');
        }

        return false;
    }

    /**
     * Допустимые ключи роли при сохранении формы (create или edit).
     *
     * @return list<string>
     */
    public static function allowedRoleKeysForAssignment(User $actor, ?User $target, int $tenantId, bool $isCreate): array
    {
        $actorRole = $actor->tenants()->where('tenant_id', $tenantId)->first()?->pivot->role;
        if ($actorRole === null || $actorRole === '') {
            return [];
        }

        if ($isCreate) {
            return self::creatableRoleKeys($actorRole);
        }

        if ($target === null) {
            return [];
        }

        if ($actor->id === $target->id) {
            return self::editableRoleKeysForSelf($actorRole);
        }

        if (! self::canEditTeamMember($actor, $target, $tenantId)) {
            return [];
        }

        $targetRole = (string) ($target->tenants()->where('tenant_id', $tenantId)->first()?->pivot->role ?? '');

        return self::editableRoleKeysForTarget($actorRole, $targetRole);
    }

    public static function assertLastOwnerNotDemoted(Tenant $tenant, string $oldRole, string $newRole): void
    {
        if ($oldRole !== 'tenant_owner' || $newRole === 'tenant_owner') {
            return;
        }

        $ownerCount = $tenant->users()->wherePivot('role', 'tenant_owner')->count();
        if ($ownerCount <= 1) {
            throw ValidationException::withMessages([
                'tenant_role' => 'Нельзя снять роль владельца, пока в команде нет другого владельца клиента.',
            ]);
        }
    }
}
