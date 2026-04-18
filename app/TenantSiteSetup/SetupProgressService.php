<?php

declare(strict_types=1);

namespace App\TenantSiteSetup;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

final class SetupProgressService
{
    public function __construct(
        private readonly SetupApplicabilityEvaluator $applicability,
        private readonly SetupCompletionEvaluator $completion,
        private readonly SetupValueSnapshotResolver $snapshots,
        private readonly SetupItemStateService $itemStates,
        private readonly SetupItemUrlResolver $urls,
    ) {}

    /**
     * Сводка прогресса по чеклисту запуска.
     *
     * Кеш осознанно привязан к пользователю: состав applicable-пунктов зависит от прав
     * ({@see SetupApplicabilityEvaluator}), поэтому ключ включает user id (см. {@see SetupProgressCache::key}).
     *
     * @param  ?User  $user  для чьих прав считать; по умолчанию текущий {@see Auth::user()} (HTTP).
     * @return array<string, mixed>
     */
    public function summary(Tenant $tenant, ?User $user = null): array
    {
        $actor = $user ?? Auth::user();
        $userId = $actor instanceof User ? $actor->getKey() : null;
        $cacheKey = SetupProgressCache::key((int) $tenant->id, $userId);

        return Cache::remember($cacheKey, 120, function () use ($tenant, $actor) {
            return $this->computeSummary($tenant, $actor instanceof User ? $actor : null);
        });
    }

    /**
     * Без кеша; для явного пользователя см. {@see summary()} ($user).
     *
     * @param  ?User  $user  по умолчанию {@see Auth::user()}.
     * @return array<string, mixed>
     */
    public function computeSummary(Tenant $tenant, ?User $user = null): array
    {
        $actor = $user ?? Auth::user();
        $evalUser = $actor instanceof User ? $actor : null;

        $definitions = SetupItemRegistry::definitions();
        $denominator = 0;
        $completedNumerator = 0;
        $launchCriticalTotal = 0;
        $launchCriticalCompleted = 0;
        $recommendedTotal = 0;
        $advancedTotal = 0;
        $quickApplicable = 0;
        $quickCompleted = 0;
        $extendedApplicable = 0;
        $extendedCompleted = 0;
        $nextPending = [];
        $completedWithSnapshots = [];
        $categorySummaries = [];

        foreach ($definitions as $key => $def) {
            if ($this->applicability->evaluateItem($tenant, $def, $evalUser) !== 'applicable') {
                continue;
            }

            $state = $this->itemStates->findState((int) $tenant->id, $key);
            $rowStatus = $state?->current_status ?? 'pending';

            if ($rowStatus === 'not_needed' && $def->notNeededAllowed) {
                continue;
            }

            $dataComplete = $this->completion->isComplete($tenant, $def);
            if ($rowStatus === 'completed' && ! $dataComplete) {
                $this->itemStates->demoteCompletedWhenDataRegressed($tenant, $def, $dataComplete);
                $rowStatus = 'pending';
            }
            if ($dataComplete && $rowStatus !== 'completed' && $rowStatus !== 'not_needed') {
                $snap = ['value' => $this->snapshots->snapshot($tenant, $def)];
                $this->itemStates->markCompletedBySystem(
                    $tenant,
                    $key,
                    $def->categoryKey,
                    $snap,
                    $def->filamentRouteName,
                );
                $rowStatus = 'completed';
            }

            $denominator++;

            if ($def->importance === SetupItemImportance::Recommended) {
                $recommendedTotal++;
            }
            if ($def->importance === SetupItemImportance::Advanced) {
                $advancedTotal++;
            }
            if ($def->launchCritical) {
                $launchCriticalTotal++;
            }

            $isCompletedRow = $rowStatus === 'completed';

            if ($def->readinessTier === SetupReadinessTier::QuickLaunch) {
                $quickApplicable++;
                if ($isCompletedRow) {
                    $quickCompleted++;
                }
            } else {
                $extendedApplicable++;
                if ($isCompletedRow) {
                    $extendedCompleted++;
                }
            }
            if ($isCompletedRow) {
                $completedNumerator++;
                $completedWithSnapshots[] = [
                    'key' => $key,
                    'title' => $def->title,
                    'snapshot' => $this->snapshots->snapshot($tenant, $def),
                    'url' => $this->urls->urlFor($tenant, $def),
                ];
                if ($def->launchCritical) {
                    $launchCriticalCompleted++;
                }
            } else {
                if (count($nextPending) < 8) {
                    $nextPending[] = [
                        'key' => $key,
                        'title' => $def->title,
                        'url' => $this->urls->urlFor($tenant, $def),
                        'launch_critical' => $def->launchCritical,
                    ];
                }
            }

            $cat = $def->categoryKey;
            if (! isset($categorySummaries[$cat])) {
                $categorySummaries[$cat] = ['applicable' => 0, 'completed' => 0];
            }
            $categorySummaries[$cat]['applicable']++;
            if ($isCompletedRow) {
                $categorySummaries[$cat]['completed']++;
            }
        }

        $denom = max(1, $denominator);
        $pct = (int) round(($completedNumerator / $denom) * 100);

        $quickDenom = max(1, $quickApplicable);
        $quickPct = (int) round(($quickCompleted / $quickDenom) * 100);
        $extDenom = max(1, $extendedApplicable);
        $extPct = (int) round(($extendedCompleted / $extDenom) * 100);

        return [
            'applicable_count' => $denominator,
            'completed_count' => $completedNumerator,
            'completion_percent' => $pct,
            'quick_launch_applicable' => $quickApplicable,
            'quick_launch_completed' => $quickCompleted,
            'quick_launch_percent' => $quickApplicable > 0 ? $quickPct : 0,
            'extended_applicable' => $extendedApplicable,
            'extended_completed' => $extendedCompleted,
            'extended_percent' => $extendedApplicable > 0 ? $extPct : 0,
            'launch_critical_total' => $launchCriticalTotal,
            'launch_critical_completed' => $launchCriticalCompleted,
            'launch_critical_remaining' => max(0, $launchCriticalTotal - $launchCriticalCompleted),
            'recommended_total' => $recommendedTotal,
            'advanced_total' => $advancedTotal,
            'next_pending_items' => array_slice($nextPending, 0, 8),
            'completed_items_with_snapshots' => $completedWithSnapshots,
            'category_summaries' => $categorySummaries,
        ];
    }
}
