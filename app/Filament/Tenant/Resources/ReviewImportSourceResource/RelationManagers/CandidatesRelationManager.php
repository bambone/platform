<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ReviewImportSourceResource\RelationManagers;

use App\Filament\Tenant\Resources\ReviewResource\Support\ReviewImportCandidateBulkActions;
use App\Models\ReviewImportCandidate;
use App\Models\ReviewImportSource;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CandidatesRelationManager extends RelationManager
{
    protected static string $relationship = 'candidates';

    protected static ?string $title = 'Кандидаты';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('author_name')->label('Автор')->placeholder('—'),
                TextColumn::make('rating')->label('Оценка')->placeholder('—'),
                TextColumn::make('body')->label('Текст')->limit(40),
                TextColumn::make('status')->badge(),
                TextColumn::make('imported_review_id')->label('Review id')->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('import_as_reviews')
                        ->label('Импортировать в отзывы')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->form(ReviewImportCandidateBulkActions::importFormFields())
                        ->action(function (BulkAction $action, array $data): void {
                            $owner = $this->getOwnerRecord();
                            if (! $owner instanceof ReviewImportSource) {
                                Notification::make()->title('Контекст источника недоступен')->danger()->send();

                                return;
                            }
                            ReviewImportCandidateBulkActions::runImport(
                                $action,
                                $data,
                                (int) $owner->tenant_id,
                                (int) $owner->id,
                            );
                        }),
                    BulkAction::make('ignore')
                        ->label('Игнорировать')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (BulkAction $action): void {
                            $owner = $this->getOwnerRecord();
                            if (! $owner instanceof ReviewImportSource) {
                                Notification::make()->title('Контекст источника недоступен')->danger()->send();

                                return;
                            }
                            ReviewImportCandidateBulkActions::runIgnore(
                                $action,
                                (int) $owner->tenant_id,
                                (int) $owner->id,
                            );
                        }),
                ]),
            ]);
    }
}
