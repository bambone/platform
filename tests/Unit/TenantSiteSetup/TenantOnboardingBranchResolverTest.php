<?php

declare(strict_types=1);

namespace Tests\Unit\TenantSiteSetup;

use App\TenantSiteSetup\TenantOnboardingBlockingReason;
use App\TenantSiteSetup\TenantOnboardingBranchConsistency;
use App\TenantSiteSetup\TenantOnboardingBranchId;
use App\TenantSiteSetup\TenantOnboardingBranchResolver;
use App\TenantSiteSetup\TenantOnboardingResolutionAction;
use Tests\TestCase;

final class TenantOnboardingBranchResolverTest extends TestCase
{
    public function test_slot_booking_module_off_downgrades_to_crm_only(): void
    {
        $r = app(TenantOnboardingBranchResolver::class)->resolveBranches(
            TenantOnboardingBranchId::SlotBooking->value,
            false,
            true,
        );

        $this->assertSame(TenantOnboardingBranchId::CrmOnly->value, $r->effectiveBranchId);
        $this->assertSame(TenantOnboardingBranchConsistency::NeedsPlatformAction, $r->consistency);
        $this->assertSame(TenantOnboardingBlockingReason::SchedulingModuleDisabled, $r->blockingReason);
        $this->assertSame(TenantOnboardingResolutionAction::PlatformEnablementRequired, $r->resolutionAction);
    }

    public function test_slot_booking_module_on_ok(): void
    {
        $r = app(TenantOnboardingBranchResolver::class)->resolveBranches(
            TenantOnboardingBranchId::SlotBooking->value,
            true,
            true,
        );

        $this->assertSame(TenantOnboardingBranchId::SlotBooking->value, $r->effectiveBranchId);
        $this->assertTrue($r->isOk());
    }

    public function test_slot_booking_module_on_no_permission_warning(): void
    {
        $r = app(TenantOnboardingBranchResolver::class)->resolveBranches(
            TenantOnboardingBranchId::SlotBooking->value,
            true,
            false,
        );

        $this->assertSame(TenantOnboardingBranchId::SlotBooking->value, $r->effectiveBranchId);
        $this->assertSame(TenantOnboardingBranchConsistency::Warning, $r->consistency);
        $this->assertSame(TenantOnboardingBlockingReason::MissingManageScheduling, $r->blockingReason);
    }

    public function test_crm_only_ignores_module_flag(): void
    {
        $r = app(TenantOnboardingBranchResolver::class)->resolveBranches(
            TenantOnboardingBranchId::CrmOnly->value,
            false,
            false,
        );

        $this->assertTrue($r->isOk());
        $this->assertSame(TenantOnboardingBranchId::CrmOnly->value, $r->effectiveBranchId);
    }

    public function test_parse_desired_from_primary_goal_booking(): void
    {
        $resolver = app(TenantOnboardingBranchResolver::class);
        $id = $resolver->parseDesiredBranchId(['primary_goal' => 'booking', 'desired_branch' => '']);

        $this->assertSame(TenantOnboardingBranchId::SlotBooking->value, $id);
    }

    public function test_parse_desired_explicit_mixed_wins_over_goal(): void
    {
        $resolver = app(TenantOnboardingBranchResolver::class);
        $id = $resolver->parseDesiredBranchId(['primary_goal' => 'leads', 'desired_branch' => 'mixed']);

        $this->assertSame(TenantOnboardingBranchId::Mixed->value, $id);
    }

    public function test_mixed_module_off_downgrades_like_slot(): void
    {
        $r = app(TenantOnboardingBranchResolver::class)->resolveBranches(
            TenantOnboardingBranchId::Mixed->value,
            false,
            true,
        );

        $this->assertSame(TenantOnboardingBranchId::CrmOnly->value, $r->effectiveBranchId);
        $this->assertSame(TenantOnboardingBranchConsistency::NeedsPlatformAction, $r->consistency);
    }
}
