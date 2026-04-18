<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Вычисляет desired_branch из профиля и effective_branch из capability (ветка ≠ capability).
 */
final class TenantOnboardingBranchResolver
{
    /**
     * Чистая логика для тестов: без Gate и БД.
     */
    public function resolveBranches(
        string $desiredBranchId,
        bool $schedulingModuleEnabled,
        bool $userCanManageScheduling,
    ): TenantOnboardingBranchResolution {
        $desired = TenantOnboardingBranchId::tryFromMvp($desiredBranchId) ?? TenantOnboardingBranchId::CrmOnly;

        $blockingReason = TenantOnboardingBlockingReason::None;
        $resolutionAction = TenantOnboardingResolutionAction::None;
        $consistency = TenantOnboardingBranchConsistency::Ok;

        $needsScheduling = $desired === TenantOnboardingBranchId::SlotBooking
            || $desired === TenantOnboardingBranchId::Mixed;

        $effective = $desired;

        if ($needsScheduling && ! $schedulingModuleEnabled) {
            $effective = TenantOnboardingBranchId::CrmOnly;
            $consistency = TenantOnboardingBranchConsistency::NeedsPlatformAction;
            $blockingReason = TenantOnboardingBlockingReason::SchedulingModuleDisabled;
            $resolutionAction = TenantOnboardingResolutionAction::PlatformEnablementRequired;
        } elseif ($needsScheduling && $schedulingModuleEnabled && ! $userCanManageScheduling) {
            $consistency = TenantOnboardingBranchConsistency::Warning;
            $blockingReason = TenantOnboardingBlockingReason::MissingManageScheduling;
            $resolutionAction = TenantOnboardingResolutionAction::UserPermissionGrant;
        }

        return new TenantOnboardingBranchResolution(
            desiredBranchId: $desired->value,
            effectiveBranchId: $effective->value,
            consistency: $consistency,
            blockingReason: $blockingReason,
            resolutionAction: $resolutionAction,
        );
    }

    /**
     * @param  array<string, mixed>  $mergedProfile  {@see SetupProfileRepository::getMerged}
     */
    public function resolve(Tenant $tenant, ?User $user, array $mergedProfile): TenantOnboardingBranchResolution
    {
        $desiredId = $this->parseDesiredBranchId($mergedProfile);
        $canSched = $user !== null && Gate::forUser($user)->allows('manage_scheduling');

        return $this->resolveBranches(
            $desiredId,
            (bool) $tenant->scheduling_module_enabled,
            $canSched,
        );
    }

    /**
     * @param  array<string, mixed>  $mergedProfile
     */
    public function parseDesiredBranchId(array $mergedProfile): string
    {
        $raw = trim((string) ($mergedProfile['desired_branch'] ?? ''));
        if ($raw !== '' && TenantOnboardingBranchId::tryFromMvp($raw) !== null) {
            return $raw;
        }

        $goal = (string) ($mergedProfile['primary_goal'] ?? '');

        return match ($goal) {
            'booking' => TenantOnboardingBranchId::SlotBooking->value,
            default => TenantOnboardingBranchId::CrmOnly->value,
        };
    }
}
