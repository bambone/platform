<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ReviewResource\Pages;

use App\Filament\Tenant\Resources\ReviewImportSourceResource;
use App\Filament\Tenant\Resources\ReviewResource;
use App\Filament\Tenant\Resources\ReviewResource\Pages\CreateReviewImportSource;
use App\Filament\Tenant\Resources\ReviewResource\Support\InteractsWithReviewSectionTabs;
use App\Models\ReviewImportSource;
use App\Models\User;
use App\Reviews\Import\ReviewImportCandidateStatus;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Список источников под {@see ReviewResource}; элементы — {@see ReviewImportSource}. */
final class ListReviewImportSources extends ListRecords
{
    use InteractsWithReviewSectionTabs;

    protected static string $resource = ReviewResource::class;

    protected static ?string $title = 'Источники импорта';

    protected function reviewSectionActiveTab(): string
    {
        return 'sources';
    }

    public function getModel(): string
    {
        return ReviewImportSource::class;
    }

    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can('viewAny', ReviewImportSource::class), 403);
    }

    protected function getTableQuery(): Builder|\Illuminate\Database\Eloquent\Relations\Relation|null
    {
        return ReviewImportSource::query()
            ->where('tenant_id', (int) (currentTenant()?->id ?? 0))
            ->withCount([
                // См. ReviewImportCandidateStatus: строковые константы = значения колонки (не PHP BackedEnum, .value неприменим).
                'candidates as cnt_new' => fn ($q) => $q->where('status', ReviewImportCandidateStatus::NEW),
                'candidates as cnt_selected' => fn ($q) => $q->where('status', ReviewImportCandidateStatus::SELECTED),
                'candidates as cnt_imported' => fn ($q) => $q->where('status', ReviewImportCandidateStatus::IMPORTED),
                'candidates as cnt_ignored' => fn ($q) => $q->where('status', ReviewImportCandidateStatus::IGNORED),
            ]);
    }

    protected function makeTable(): Table
    {
        $table = $this->makeBaseTable()
            ->query(fn (): Builder => $this->getTableQuery())
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->modelLabel('источник')
            ->pluralModelLabel('источники');

        $table = ReviewImportSourceResource::table($table);

        return $table
            ->recordUrl(fn (Model $record): ?string => $record instanceof ReviewImportSource
                ? EditReviewImportSource::getUrl(['record' => $record])
                : null)
            ->recordActions([
                EditAction::make()
                    ->label('Открыть')
                    ->url(fn (ReviewImportSource $record): string => EditReviewImportSource::getUrl(['record' => $record])),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_source')
                ->label('Добавить источник')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => CreateReviewImportSource::getUrl()),
        ];
    }
}
