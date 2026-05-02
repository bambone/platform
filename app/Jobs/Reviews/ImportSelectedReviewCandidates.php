<?php

declare(strict_types=1);

namespace App\Jobs\Reviews;

use App\Models\ReviewImportCandidate;
use App\Services\Reviews\Imports\Dto\ReviewCandidateImportResult;
use App\Services\Reviews\Imports\ReviewCandidateImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class ImportSelectedReviewCandidates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    /**
     * @param  list<int>  $candidateIds
     */
    public function __construct(
        public array $candidateIds,
        public int $expectedTenantId,
        public bool $publishImmediately = false,
        public ?int $forcedRating = null,
    ) {
        $this->timeout = (int) config('reviews.import.timeout', 60);
        $this->onQueue((string) config('reviews.import.queue', 'default'));
    }

    public function handle(ReviewCandidateImportService $importer): void
    {
        $ids = Collection::make($this->candidateIds)
            ->map(fn ($v): int => (int) $v)
            ->filter(fn (int $v): bool => $v > 0)
            ->unique()
            ->values();

        $merged = new ReviewCandidateImportResult(importedReviewIds: [], skippedAlreadyImportedCount: 0, errors: []);

        foreach ($ids->chunk(500) as $chunk) {
            $candidates = ReviewImportCandidate::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $this->expectedTenantId)
                ->whereIn('id', $chunk->all())
                ->orderBy('id')
                ->get();

            $merged = $merged->mergedWith(
                $importer->importCandidates(
                    $candidates,
                    $this->publishImmediately,
                    $this->forcedRating,
                    $this->expectedTenantId,
                ),
            );
        }

        $result = $merged;
        if ($result->errors !== []) {
            Log::warning('reviews.import_selected_candidates_partial_failure', [
                'candidate_ids' => $this->candidateIds,
                'tenant_id' => $this->expectedTenantId,
                'imported' => $result->importedCount(),
                'skipped_already_imported' => $result->skippedAlreadyImportedCount,
                'error_count' => count($result->errors),
                'sample' => array_slice($result->errors, 0, 3),
            ]);
        }
    }
}
