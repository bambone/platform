<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Models\Tenant;
use App\Models\User;

/**
 * Считает по каждому треку: пункты реестра, N/A по системе, знаменатель чеклиста и завершённые.
 */
final class SetupTrackApplicabilitySummaryService
{
    public function __construct(
        private readonly SetupApplicabilityEvaluator $applicability,
        private readonly SetupCompletionEvaluator $completion,
        private readonly SetupItemStateService $itemStates,
    ) {}

    /**
     * @return array<string, SetupTrackApplicabilityMetrics> keyed by SetupOnboardingTrack::value
     */
    public function summarize(Tenant $tenant, ?User $user = null): array
    {
        $definitions = SetupItemRegistry::definitions();
        $byTrack = [];

        foreach (SetupOnboardingTrack::cases() as $track) {
            $byTrack[$track->value] = [
                'total' => 0,
                'not_applicable' => 0,
                'applicable' => 0,
                'completed' => 0,
            ];
        }

        foreach ($definitions as $key => $def) {
            $trackValue = $def->resolvedOnboardingTrack()->value;
            if (! isset($byTrack[$trackValue])) {
                continue;
            }

            $byTrack[$trackValue]['total']++;

            if ($this->applicability->evaluateItem($tenant, $def, $user) !== 'applicable') {
                $byTrack[$trackValue]['not_applicable']++;

                continue;
            }

            $state = $this->itemStates->findState((int) $tenant->id, $key);
            $rowStatus = $state?->current_status ?? 'pending';
            if ($rowStatus === 'not_needed' && $def->notNeededAllowed) {
                continue;
            }

            $byTrack[$trackValue]['applicable']++;

            $dataComplete = $this->completion->isComplete($tenant, $def);
            if ($dataComplete && $rowStatus !== 'completed' && $rowStatus !== 'not_needed') {
                $rowStatus = 'completed';
            }
            if ($rowStatus === 'completed') {
                $byTrack[$trackValue]['completed']++;
            }
        }

        $out = [];
        foreach ($byTrack as $trackValue => $row) {
            $out[$trackValue] = new SetupTrackApplicabilityMetrics(
                itemsTotal: (int) $row['total'],
                itemsApplicable: (int) $row['applicable'],
                itemsNotApplicableBySystem: (int) $row['not_applicable'],
                itemsCompleted: (int) $row['completed'],
            );
        }

        return $out;
    }
}
