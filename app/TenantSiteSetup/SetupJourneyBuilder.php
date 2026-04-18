<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Models\Tenant;
use App\Models\TenantSetupItemState;
use App\Models\User;

final class SetupJourneyBuilder
{
    public function __construct(
        private readonly SetupApplicabilityEvaluator $applicability,
        private readonly SetupCompletionEvaluator $completion,
        private readonly SetupTracksResolver $tracksResolver,
        private readonly SetupJourneyOrdering $journeyOrdering,
        private readonly SetupProfileRepository $profiles,
    ) {}

    /**
     * Ordered item keys for guided mode (skip not applicable; skip completed unless forced).
     *
     * @return list<string>
     */
    public function visibleStepKeys(Tenant $tenant, ?User $user = null): array
    {
        $definitions = collect(SetupItemRegistry::definitions())
            ->sortBy(fn (SetupItemDefinition $d) => $d->sortOrder);

        $keys = [];
        $snapshot = SetupCapabilitySnapshot::capture($tenant, $user);
        $tracks = $this->tracksResolver->resolve($tenant, $user, $this->profiles->getMerged((int) $tenant->id), $snapshot);
        $activeTrackSet = array_flip($tracks->activeTracks);

        foreach ($definitions as $key => $def) {
            if ($this->applicability->evaluateItem($tenant, $def, $user) !== 'applicable') {
                continue;
            }
            $track = $def->resolvedOnboardingTrack()->value;
            if ($track !== SetupOnboardingTrack::Base->value
                && ! isset($activeTrackSet[$track])) {
                continue;
            }
            $state = TenantSetupItemState::query()
                ->where('tenant_id', $tenant->id)
                ->where('item_key', $key)
                ->value('current_status');
            if ($state === 'snoozed') {
                continue;
            }
            if ($state === 'not_needed') {
                continue;
            }
            if ($this->completion->isComplete($tenant, $def) || $state === 'completed') {
                continue;
            }
            $keys[] = $key;
        }

        return $this->journeyOrdering->applyProfileOrdering($tenant, $keys, $user);
    }
}
