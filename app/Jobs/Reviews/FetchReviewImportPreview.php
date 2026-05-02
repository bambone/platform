<?php

declare(strict_types=1);

namespace App\Jobs\Reviews;

use App\Models\ReviewImportSource;
use App\Services\Reviews\Imports\ReviewImportPreviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class FetchReviewImportPreview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public function __construct(
        public int $sourceId,
        public int $expectedTenantId,
    ) {
        $this->timeout = (int) config('reviews.import.timeout', 60);
        $this->onQueue((string) config('reviews.import.queue', 'default'));
    }

    public function handle(ReviewImportPreviewService $preview): void
    {
        $source = ReviewImportSource::query()->withoutGlobalScopes()->find($this->sourceId);
        if ($source === null) {
            Log::warning('reviews.fetch_preview_source_missing', [
                'source_id' => $this->sourceId,
            ]);

            return;
        }

        if ((int) $source->tenant_id !== $this->expectedTenantId) {
            Log::warning('reviews.fetch_preview_tenant_mismatch', [
                'source_id' => $this->sourceId,
                'expected_tenant_id' => $this->expectedTenantId,
                'actual_tenant_id' => (int) $source->tenant_id,
            ]);

            return;
        }

        $preview->runPreview($source);
    }
}
