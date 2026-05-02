<?php

declare(strict_types=1);

namespace Tests\Feature\Reviews;

use App\Jobs\Reviews\FetchReviewImportPreview;
use App\Models\ReviewImportRun;
use App\Models\ReviewImportSource;
use App\Reviews\Import\ReviewImportSourceStatus;
use App\Services\Reviews\Imports\ReviewImportPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

final class FetchReviewImportPreviewTenantGuardTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_tenant_mismatch_does_not_call_preview_service(): void
    {
        $tenantOne = $this->createTenantWithActiveDomain('ri-preview-one');
        $tenantTwo = $this->createTenantWithActiveDomain('ri-preview-two');

        $source = ReviewImportSource::query()->withoutGlobalScopes()->create([
            'tenant_id' => (int) $tenantOne->id,
            'provider' => 'manual',
            'title' => 'Manual',
            'source_url' => 'https://example.com/m',
            'status' => ReviewImportSourceStatus::READY,
        ]);

        $before = ReviewImportRun::query()->where('review_import_source_id', $source->id)->count();

        $job = new FetchReviewImportPreview((int) $source->id, (int) $tenantTwo->id);
        $job->handle(app(ReviewImportPreviewService::class));

        $after = ReviewImportRun::query()->where('review_import_source_id', $source->id)->count();
        $this->assertSame($before, $after);
    }

    public function test_matching_tenant_runs_preview(): void
    {
        $tenant = $this->createTenantWithActiveDomain('ri-preview-ok');

        $source = ReviewImportSource::query()->withoutGlobalScopes()->create([
            'tenant_id' => (int) $tenant->id,
            'provider' => 'manual',
            'title' => 'Manual',
            'source_url' => 'https://example.com/m2',
            'status' => ReviewImportSourceStatus::READY,
        ]);

        $job = new FetchReviewImportPreview((int) $source->id, (int) $tenant->id);
        $job->handle(app(ReviewImportPreviewService::class));

        $this->assertSame(1, ReviewImportRun::query()->where('review_import_source_id', $source->id)->count());
    }
}
