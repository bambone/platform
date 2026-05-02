<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ReviewResource\Support;

use App\Models\ReviewImportCandidate;
use App\Reviews\Import\ReviewImportCandidateStatus;
use App\Services\Reviews\Imports\ReviewCandidateImportService;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Общая логика массовых действий над {@see ReviewImportCandidate} для списка и relation manager источников.
 */
final class ReviewImportCandidateBulkActions
{
    /**
     * @return array<int, Toggle|Select>
     */
    public static function importFormFields(): array
    {
        return [
            Toggle::make('publish')
                ->label('Опубликовать сразу')
                ->default(false),
            Select::make('rating')
                ->label('Оценка при импорте')
                ->options([
                    '' => 'Как у кандидата / без оценки',
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                ])
                ->native(true),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $selectedRecords
     * @return Collection<int, ReviewImportCandidate>
     */
    public static function eligibleRecords(
        Collection $selectedRecords,
        int $tenantId,
        ?int $restrictToReviewImportSourceId = null,
    ): Collection {
        return $selectedRecords
            ->filter(fn (mixed $c): bool => $c instanceof ReviewImportCandidate
                && (int) $c->tenant_id === $tenantId
                && (
                    $restrictToReviewImportSourceId === null
                    || (int) $c->review_import_source_id === $restrictToReviewImportSourceId
                )
                && $c->status !== ReviewImportCandidateStatus::IMPORTED)
            ->values();
    }

    public static function runImport(BulkAction $action, array $data, int $tenantId, ?int $restrictToReviewImportSourceId = null): void
    {
        $records = self::eligibleRecords($action->getSelectedRecords(), $tenantId, $restrictToReviewImportSourceId);

        if ($records->isEmpty()) {
            Notification::make()->title('Нет строк для импорта')->warning()->send();

            return;
        }

        $publish = (bool) ($data['publish'] ?? false);
        $forced = isset($data['rating']) && $data['rating'] !== '' && $data['rating'] !== null
            ? (int) $data['rating']
            : null;

        $result = app(ReviewCandidateImportService::class)->importCandidates($records, $publish, $forced, $tenantId);
        $notice = Notification::make()->title(implode(' · ', $result->summaryLines()));
        if ($result->errors !== []) {
            $notice->body($result->formattedBodyWithErrors())->warning()->send();

            return;
        }
        $notice->success()->send();
    }

    public static function runIgnore(BulkAction $action, int $tenantId, ?int $restrictToReviewImportSourceId = null): void
    {
        $ids = self::eligibleRecords($action->getSelectedRecords(), $tenantId, $restrictToReviewImportSourceId)
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            Notification::make()->title('Нет строк для игнора')->warning()->send();

            return;
        }

        /** @see ReviewImportCandidateStatus строковые константы = значения колонки `status` в БД (не PHP BackedEnum). */
        $q = ReviewImportCandidate::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->where('status', '!=', ReviewImportCandidateStatus::IMPORTED);

        if ($restrictToReviewImportSourceId !== null) {
            $q->where('review_import_source_id', $restrictToReviewImportSourceId);
        }

        $updated = $q->update(['status' => ReviewImportCandidateStatus::IGNORED]);

        Notification::make()
            ->title('Отмечено как игнор'.($updated > 0 ? ': '.$updated : ''))
            ->success()
            ->send();
    }
}
