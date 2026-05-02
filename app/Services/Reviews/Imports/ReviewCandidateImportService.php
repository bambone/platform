<?php

declare(strict_types=1);

namespace App\Services\Reviews\Imports;

use App\Models\Review;
use App\Models\ReviewImportCandidate;
use App\Reviews\Import\ReviewImportCandidateStatus;
use App\Services\Reviews\Imports\Dto\ReviewCandidateImportResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final class ReviewCandidateImportService
{
    public function __construct(
        private readonly ReviewAvatarImportService $avatars,
    ) {}

    /**
     * @param  iterable<ReviewImportCandidate>  $candidates
     */
    public function importCandidates(
        iterable $candidates,
        bool $publishImmediately,
        ?int $forcedRating = null,
        ?int $expectedTenantId = null,
    ): ReviewCandidateImportResult {
        if ($forcedRating !== null && ($forcedRating < 1 || $forcedRating > 5)) {
            throw new InvalidArgumentException('Forced rating must be between 1 and 5.');
        }

        $ids = [];
        $skipped = 0;
        $errors = [];
        foreach ($candidates as $candidate) {
            if ($expectedTenantId !== null && (int) $candidate->tenant_id !== $expectedTenantId) {
                $errors[] = 'Кандидат #'.$candidate->id.': несовпадение клиента (tenant).';

                continue;
            }
            if ($candidate->status === ReviewImportCandidateStatus::IMPORTED) {
                $skipped++;

                continue;
            }
            try {
                $txOutcome = DB::transaction(function () use ($candidate, $publishImmediately, $forcedRating, $expectedTenantId): array {
                    $locked = ReviewImportCandidate::query()
                        ->withoutGlobalScopes()
                        ->when(
                            $expectedTenantId !== null,
                            fn (Builder $q): Builder => $q->where('tenant_id', $expectedTenantId),
                        )
                        ->whereKey($candidate->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $locked instanceof ReviewImportCandidate) {
                        return ['state' => 'abort'];
                    }

                    if ($locked->status === ReviewImportCandidateStatus::IMPORTED) {
                        return ['state' => 'skipped_already_imported'];
                    }

                    $rating = $forcedRating ?? $locked->rating;

                    $review = new Review;
                    $review->tenant_id = $locked->tenant_id;
                    $review->name = $locked->author_name ?: 'Гость';
                    $review->city = null;
                    $review->body = $locked->body;
                    $review->rating = $rating;
                    $review->status = $publishImmediately ? 'published' : 'draft';
                    $review->is_featured = false;
                    $review->sort_order = 5000;
                    $review->source = 'import';
                    $review->source_provider = $locked->provider;
                    $review->source_external_id = $locked->external_review_id;
                    $review->source_url = $locked->source_url;
                    $review->review_import_source_id = $locked->review_import_source_id;
                    $review->source_original_body = $locked->body;
                    $review->source_payload_json = $locked->raw_payload_json;
                    $review->imported_at = now();
                    $review->date = $locked->reviewed_at?->toDateString();
                    $review->media_type = 'text';
                    if (filled($locked->author_avatar_url)) {
                        $review->meta_json = array_merge($review->meta_json ?? [], [
                            'avatar_external_url' => $locked->author_avatar_url,
                        ]);
                    }
                    $review->save();

                    $locked->imported_review_id = $review->id;
                    $locked->status = ReviewImportCandidateStatus::IMPORTED;
                    $locked->save();

                    $review->review_import_candidate_id = $locked->id;
                    $review->save();

                    return [
                        'state' => 'imported',
                        'review_id' => $review->id,
                        'tenant_id' => (int) $locked->tenant_id,
                        'provider' => (string) $locked->provider,
                        'avatar_url' => $locked->author_avatar_url,
                    ];
                });

                if (($txOutcome['state'] ?? '') === 'skipped_already_imported') {
                    $skipped++;

                    continue;
                }

                if (($txOutcome['state'] ?? '') !== 'imported' || ! isset($txOutcome['review_id'])) {
                    continue;
                }

                $ids[] = (int) $txOutcome['review_id'];

                if (config('reviews.import.download_avatars', true) && filled($txOutcome['avatar_url'] ?? null)) {
                    try {
                        $pathAvatar = $this->avatars->downloadToTenantPublic(
                            (string) $txOutcome['avatar_url'],
                            (int) $txOutcome['tenant_id'],
                            (string) $txOutcome['provider'],
                        );
                        if ($pathAvatar) {
                            Review::query()
                                ->withoutGlobalScopes()
                                ->whereKey($txOutcome['review_id'])
                                ->update(['avatar' => $pathAvatar]);
                        }
                    } catch (Throwable $avatarEx) {
                        Log::warning('reviews.import_avatar_download_failed', [
                            'review_id' => $txOutcome['review_id'],
                            'candidate_id' => $candidate->id,
                            'message' => self::sanitizeExceptionMessage($avatarEx),
                        ]);
                    }
                }
            } catch (Throwable $e) {
                $errors[] = 'Кандидат #'.$candidate->id.': '.self::sanitizeExceptionMessage($e);
            }
        }

        return new ReviewCandidateImportResult(
            importedReviewIds: $ids,
            skippedAlreadyImportedCount: $skipped,
            errors: $errors,
        );
    }

    public static function sanitizeExceptionMessage(Throwable $e): string
    {
        $msg = preg_replace('/\s+/', ' ', $e->getMessage()) ?: 'ошибка';
        $msg = (string) $msg;
        $msg = preg_replace('/([?&](?:token|key|api_key|access_token|signature|secret|password)=)[^&\s]+/i', '$1***', $msg) ?? $msg;
        $msg = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer ***', $msg) ?? $msg;
        $msg = preg_replace('/https?:\/\/[^\s]+\?[^\s]+/i', '[url]', $msg) ?? $msg;

        return mb_substr($msg, 0, 160);
    }
}
