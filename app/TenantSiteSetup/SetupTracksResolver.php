<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Models\Tenant;
use App\Models\User;

/**
 * Включает/выключает дорожки по снимку возможностей; слои — фиксированный набор readiness.
 */
final class SetupTracksResolver
{
    public function __construct(
        private readonly TenantOnboardingBranchResolver $branchResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $mergedProfile  {@see SetupProfileRepository::getMerged}
     */
    public function resolve(Tenant $tenant, ?User $user, array $mergedProfile, SetupCapabilitySnapshot $snapshot): ResolvedSetupTracks
    {
        unset($tenant, $user);

        $active = [
            SetupOnboardingTrack::Base->value,
            SetupOnboardingTrack::Branding->value,
            SetupOnboardingTrack::Contacts->value,
            SetupOnboardingTrack::Content->value,
            SetupOnboardingTrack::Programs->value,
            SetupOnboardingTrack::Seo->value,
            SetupOnboardingTrack::Catalog->value,
        ];
        $suppressed = [];

        if (! $snapshot->schedulingModuleEnabled) {
            $suppressed[SetupOnboardingTrack::Scheduling->value] = 'scheduling_module_disabled';
        } elseif (! $snapshot->userCanManageScheduling) {
            $suppressed[SetupOnboardingTrack::Scheduling->value] = 'permission_or_surface_missing';
        } else {
            $active[] = SetupOnboardingTrack::Scheduling->value;
        }

        $notificationsOk = $snapshot->userCanManageNotifications
            || $snapshot->userCanManageNotificationDestinations
            || $snapshot->userCanManageNotificationSubscriptions;
        if (! $notificationsOk) {
            $suppressed[SetupOnboardingTrack::Notifications->value] = 'permission_or_surface_missing';
        } else {
            $active[] = SetupOnboardingTrack::Notifications->value;
        }

        if (! $snapshot->userCanManageReviews) {
            $suppressed[SetupOnboardingTrack::Reviews->value] = 'permission_or_surface_missing';
        } else {
            $active[] = SetupOnboardingTrack::Reviews->value;
        }

        if (! $snapshot->pushSectionVisibleToUser) {
            $suppressed[SetupOnboardingTrack::Push->value] = 'feature_or_plan';
        } else {
            $active[] = SetupOnboardingTrack::Push->value;
        }

        $desiredId = $this->branchResolver->parseDesiredBranchId($mergedProfile);
        $branchResolution = $this->branchResolver->resolveBranches(
            $desiredId,
            $snapshot->schedulingModuleEnabled,
            $snapshot->userCanManageScheduling,
        );
        if ($branchResolution->effectiveBranchId === TenantOnboardingBranchId::CrmOnly->value) {
            $schedKey = SetupOnboardingTrack::Scheduling->value;
            $active = array_values(array_filter(
                $active,
                static fn (string $t): bool => $t !== $schedKey,
            ));
            if (! array_key_exists($schedKey, $suppressed)) {
                $suppressed[$schedKey] = 'onboarding_branch_crm_only';
            }
        }

        $layers = [
            SetupOnboardingLayer::QuickLaunch->value,
            SetupOnboardingLayer::PublicReadiness->value,
            SetupOnboardingLayer::OperationalReadiness->value,
            SetupOnboardingLayer::GrowthReadiness->value,
        ];

        return new ResolvedSetupTracks(
            array_values(array_unique($active)),
            $layers,
            $suppressed,
        );
    }
}
