<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Concerns;

use App\Models\User;
use App\TenantSiteSetup\SetupProfileRepository;
use App\TenantSiteSetup\TenantOnboardingBranchResolution;
use App\TenantSiteSetup\TenantOnboardingBranchResolver;
use Illuminate\Support\Facades\Auth;

trait ResolvesTenantOnboardingBranch
{
    public function getBranchResolutionProperty(): TenantOnboardingBranchResolution
    {
        $tenant = currentTenant();
        if ($tenant === null) {
            return app(TenantOnboardingBranchResolver::class)->resolveBranches(
                \App\TenantSiteSetup\TenantOnboardingBranchId::CrmOnly->value,
                false,
                false,
            );
        }

        $user = Auth::user();

        return app(TenantOnboardingBranchResolver::class)->resolve(
            $tenant,
            $user instanceof User ? $user : null,
            app(SetupProfileRepository::class)->getMerged((int) $tenant->id),
        );
    }
}
