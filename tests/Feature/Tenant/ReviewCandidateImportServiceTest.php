<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Jobs\Reviews\ImportSelectedReviewCandidates;
use App\Models\ReviewImportCandidate;
use App\Models\ReviewImportSource;
use App\Reviews\Import\ReviewImportCandidateStatus;
use App\Reviews\Import\ReviewImportSourceStatus;
use App\Services\Reviews\Imports\ReviewCandidateImportService;
use App\Services\Reviews\Imports\ReviewImportDedupe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\CreatesTenantsWithDomains;
use Tests\TestCase;

final class ReviewCandidateImportServiceTest extends TestCase
{
    use CreatesTenantsWithDomains;
    use RefreshDatabase;

    public function test_import_creates_draft_review_and_marks_candidate_imported(): void
    {
        Config::set('reviews.import.download_avatars', false);

        $tenant = $this->createTenantWithActiveDomain('rev-import');
        $tid = (int) $tenant->id;

        $source = ReviewImportSource::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tid,
            'provider' => 'manual',
            'title' => 'CSV',
            'source_url' => 'https://example.com/manual',
            'status' => ReviewImportSourceStatus::READY,
        ]);

        $hash = ReviewImportDedupe::hashNoExternal('manual', 'Пётр', null, 'Текст отзыва для импорта достаточной длины.');
        $candidate = ReviewImportCandidate::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tid,
            'review_import_source_id' => $source->id,
            'provider' => 'manual',
            'dedupe_hash' => $hash,
            'author_name' => 'Пётр',
            'body' => 'Текст отзыва для импорта достаточной длины.',
            'status' => ReviewImportCandidateStatus::NEW,
            'rating' => null,
        ]);

        $service = app(ReviewCandidateImportService::class);
        $result = $service->importCandidates([$candidate], false, null, $tid);

        $this->assertCount(1, $result->importedReviewIds);
        $candidate->refresh();
        $this->assertSame(ReviewImportCandidateStatus::IMPORTED, $candidate->status);
        $this->assertNotNull($candidate->imported_review_id);

        $this->assertDatabaseHas('reviews', [
            'id' => $candidate->imported_review_id,
            'tenant_id' => $tid,
            'body' => 'Текст отзыва для импорта достаточной длины.',
            'status' => 'draft',
            'source' => 'import',
        ]);
    }

    public function test_second_import_skips_already_imported_candidate(): void
    {
        Config::set('reviews.import.download_avatars', false);

        $tenant = $this->createTenantWithActiveDomain('rev-import2');
        $tid = (int) $tenant->id;

        $source = ReviewImportSource::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tid,
            'provider' => 'manual',
            'title' => 'CSV',
            'source_url' => 'https://example.com/manual',
            'status' => ReviewImportSourceStatus::READY,
        ]);

        $hash = ReviewImportDedupe::hashNoExternal('manual', 'Анна', null, 'Другой текст отзыва для импорта в сервис.');
        $candidate = ReviewImportCandidate::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tid,
            'review_import_source_id' => $source->id,
            'provider' => 'manual',
            'dedupe_hash' => $hash,
            'author_name' => 'Анна',
            'body' => 'Другой текст отзыва для импорта в сервис.',
            'status' => ReviewImportCandidateStatus::NEW,
        ]);

        $service = app(ReviewCandidateImportService::class);
        $first = $service->importCandidates([$candidate], false, null, $tid);
        $second = $service->importCandidates([$candidate->fresh()], false, null, $tid);

        $this->assertCount(1, $first->importedReviewIds);
        $this->assertSame(0, $second->importedCount());
        $this->assertSame(1, $second->skippedAlreadyImportedCount);
    }

    public function test_import_with_expected_wrong_tenant_records_error_without_creating_review(): void
    {
        Config::set('reviews.import.download_avatars', false);

        $tenantA = $this->createTenantWithActiveDomain('rev-import-wrong-a');
        $tenantB = $this->createTenantWithActiveDomain('rev-import-wrong-b');
        $tidA = (int) $tenantA->id;
        $tidB = (int) $tenantB->id;

        $source = ReviewImportSource::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tidA,
            'provider' => 'manual',
            'title' => 'CSV',
            'source_url' => 'https://example.com/manual',
            'status' => ReviewImportSourceStatus::READY,
        ]);

        $hash = ReviewImportDedupe::hashNoExternal('manual', 'Иван', null, 'Уникальный текст для проверки tenant guard.');
        $candidate = ReviewImportCandidate::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tidA,
            'review_import_source_id' => $source->id,
            'provider' => 'manual',
            'dedupe_hash' => $hash,
            'author_name' => 'Иван',
            'body' => 'Уникальный текст для проверки tenant guard.',
            'status' => ReviewImportCandidateStatus::NEW,
        ]);

        $service = app(ReviewCandidateImportService::class);
        $result = $service->importCandidates([$candidate], false, null, $tidB);

        $this->assertSame([], $result->importedReviewIds);
        $this->assertCount(1, $result->errors);
        $candidate->refresh();
        $this->assertSame(ReviewImportCandidateStatus::NEW, $candidate->status);

        $this->assertDatabaseMissing('reviews', [
            'body' => 'Уникальный текст для проверки tenant guard.',
            'tenant_id' => $tidA,
        ]);
    }

    public function test_invalid_forced_rating_throws_invalid_argument_exception(): void
    {
        Config::set('reviews.import.download_avatars', false);

        $tenant = $this->createTenantWithActiveDomain('rev-import-rating');
        $tid = (int) $tenant->id;

        $source = ReviewImportSource::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tid,
            'provider' => 'manual',
            'title' => 'CSV',
            'source_url' => 'https://example.com/manual',
            'status' => ReviewImportSourceStatus::READY,
        ]);

        $hash = ReviewImportDedupe::hashNoExternal('manual', 'Олеся', null, 'Ещё один текст достаточной длины для импорта.');
        $candidate = ReviewImportCandidate::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tid,
            'review_import_source_id' => $source->id,
            'provider' => 'manual',
            'dedupe_hash' => $hash,
            'author_name' => 'Олеся',
            'body' => 'Ещё один текст достаточной длины для импорта.',
            'status' => ReviewImportCandidateStatus::NEW,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(ReviewCandidateImportService::class)->importCandidates([$candidate], false, 6, $tid);
    }

    public function test_import_job_restricts_candidates_to_expected_tenant(): void
    {
        Config::set('reviews.import.download_avatars', false);

        $tenantA = $this->createTenantWithActiveDomain('rev-job-a');
        $tenantB = $this->createTenantWithActiveDomain('rev-job-b');
        $tidA = (int) $tenantA->id;
        $tidB = (int) $tenantB->id;

        $srcA = ReviewImportSource::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tidA,
            'provider' => 'manual',
            'title' => 'CSV A',
            'source_url' => 'https://example.com/a',
            'status' => ReviewImportSourceStatus::READY,
        ]);
        $srcB = ReviewImportSource::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tidB,
            'provider' => 'manual',
            'title' => 'CSV B',
            'source_url' => 'https://example.com/b',
            'status' => ReviewImportSourceStatus::READY,
        ]);

        $hA = ReviewImportDedupe::hashNoExternal('manual', 'Юра', null, 'Текст кандидата A для job guard.');
        $cA = ReviewImportCandidate::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tidA,
            'review_import_source_id' => $srcA->id,
            'provider' => 'manual',
            'dedupe_hash' => $hA,
            'author_name' => 'Юра',
            'body' => 'Текст кандидата A для job guard.',
            'status' => ReviewImportCandidateStatus::NEW,
        ]);
        $hB = ReviewImportDedupe::hashNoExternal('manual', 'Катя', null, 'Текст кандидата B только для другого клиента.');
        $cB = ReviewImportCandidate::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tidB,
            'review_import_source_id' => $srcB->id,
            'provider' => 'manual',
            'dedupe_hash' => $hB,
            'author_name' => 'Катя',
            'body' => 'Текст кандидата B только для другого клиента.',
            'status' => ReviewImportCandidateStatus::NEW,
        ]);

        $job = new ImportSelectedReviewCandidates([(int) $cA->id, (int) $cB->id], $tidA);
        $job->handle(app(ReviewCandidateImportService::class));

        $cA->refresh();
        $cB->refresh();
        $this->assertSame(ReviewImportCandidateStatus::IMPORTED, $cA->status);
        $this->assertSame(ReviewImportCandidateStatus::NEW, $cB->status);
    }
}
