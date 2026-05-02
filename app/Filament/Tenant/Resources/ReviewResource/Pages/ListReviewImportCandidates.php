<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ReviewResource\Pages;

use App\Filament\Tenant\Resources\ReviewResource;
use App\Filament\Tenant\Resources\ReviewResource\Support\InteractsWithReviewSectionTabs;
use App\Filament\Tenant\Resources\ReviewResource\Support\ReviewImportCandidateBulkActions;
use App\Models\ReviewImportCandidate;
use App\Models\ReviewImportSource;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/** Кандидаты импорта под {@see ReviewResource}; записи — {@see ReviewImportCandidate}. */
final class ListReviewImportCandidates extends ListRecords
{
    use InteractsWithReviewSectionTabs;

    protected static string $resource = ReviewResource::class;

    protected static ?string $title = 'Кандидаты импорта';

    protected function reviewSectionActiveTab(): string
    {
        return 'candidates';
    }

    public function getModel(): string
    {
        return ReviewImportCandidate::class;
    }

    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can('viewAny', ReviewImportCandidate::class), 403);
    }

    protected function getTableQuery(): Builder|\Illuminate\Database\Eloquent\Relations\Relation|null
    {
        return ReviewImportCandidate::query()
            ->where('tenant_id', (int) (currentTenant()?->id ?? 0))
            ->with(['source:id,title']);
    }

    protected function makeTable(): Table
    {
        $table = $this->makeBaseTable()
            ->query(fn (): Builder => $this->getTableQuery())
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->modelLabel('кандидат')
            ->pluralModelLabel('кандидаты');

        return $this->configureCandidatesTable($table);
    }

    protected function configureCandidatesTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('source.title')->label('Источник')->placeholder('—')->sortable(),
                TextColumn::make('author_name')->label('Автор')->placeholder('—')->searchable(),
                TextColumn::make('rating')->label('Оценка')->placeholder('—'),
                TextColumn::make('body')->label('Текст')->limit(48),
                TextColumn::make('status')->badge(),
                TextColumn::make('imported_review_id')
                    ->label('Отзыв')
                    ->placeholder('—')
                    ->url(fn (ReviewImportCandidate $r): ?string => $r->imported_review_id !== null
                        ? EditReview::getUrl(['record' => $r->imported_review_id])
                        : null),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100])
            ->filters([
                SelectFilter::make('review_import_source_id')
                    ->label('Источник')
                    ->options(fn (): array => ReviewImportSource::query()
                        ->where('tenant_id', (int) (currentTenant()?->id ?? 0))
                        ->orderBy('title')
                        ->get()
                        ->mapWithKeys(fn (ReviewImportSource $s): array => [
                            (string) $s->id => ($s->title !== null && $s->title !== '') ? $s->title : 'Источник #'.$s->id])
                        ->all()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('import_as_reviews')
                        ->label('Импортировать в отзывы')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->form(ReviewImportCandidateBulkActions::importFormFields())
                        ->action(function (BulkAction $action, array $data): void {
                            $tenantId = (int) (currentTenant()?->id ?? 0);
                            ReviewImportCandidateBulkActions::runImport($action, $data, $tenantId);
                        }),
                    BulkAction::make('ignore')
                        ->label('Игнорировать')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (BulkAction $action): void {
                            $tenantId = (int) (currentTenant()?->id ?? 0);
                            ReviewImportCandidateBulkActions::runIgnore($action, $tenantId);
                        }),
                ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('sources')
                ->label('К источникам')
                ->url(ListReviewImportSources::getUrl())
                ->color('gray'),
        ];
    }
}
