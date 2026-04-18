@php
    /** @var \App\TenantSiteSetup\TenantOnboardingBranchResolution $resolution */
    $resolution = $resolution ?? null;
@endphp
@if($resolution instanceof \App\TenantSiteSetup\TenantOnboardingBranchResolution)
    @php
        $desiredLabel = \App\TenantSiteSetup\TenantOnboardingBranchId::tryFrom($resolution->desiredBranchId)?->label() ?? $resolution->desiredBranchId;
        $effectiveLabel = \App\TenantSiteSetup\TenantOnboardingBranchId::tryFrom($resolution->effectiveBranchId)?->label() ?? $resolution->effectiveBranchId;
    @endphp
    @if(! $resolution->isOk())
        <div
            role="alert"
            class="mb-4 rounded-xl border p-4 text-sm                @if($resolution->consistency === \App\TenantSiteSetup\TenantOnboardingBranchConsistency::NeedsPlatformAction) border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-500/40 dark:bg-amber-950/35 dark:text-amber-100
                @elseif($resolution->consistency === \App\TenantSiteSetup\TenantOnboardingBranchConsistency::Warning) border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-500/40 dark:bg-sky-950/40 dark:text-sky-100
                @else border-red-300 bg-red-50 text-red-950 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-100 @endif"
        >
            <p class="font-semibold">{{ $resolution->consistency->label() }}</p>
            <p class="mt-1 text-xs opacity-90">
                Выбрано: <span class="font-medium">{{ $desiredLabel }}</span>
                @if($resolution->desiredBranchId !== $resolution->effectiveBranchId)
                    · Фактически ведём сейчас: <span class="font-medium">{{ $effectiveLabel }}</span>
                @endif
            </p>
            @if($resolution->blockingReason !== \App\TenantSiteSetup\TenantOnboardingBlockingReason::None)
                <p class="mt-2 text-xs">{{ $resolution->blockingReason->label() }}</p>
            @endif
            @if($resolution->resolutionAction !== \App\TenantSiteSetup\TenantOnboardingResolutionAction::None)
                <p class="mt-1 text-xs font-medium">{{ $resolution->resolutionAction->label() }}</p>
            @endif
        </div>
    @else
        <p class="mb-4 text-xs text-gray-600 dark:text-gray-400">
            Сценарий запуска согласован: {{ $effectiveLabel }}.
        </p>
    @endif
@endif
