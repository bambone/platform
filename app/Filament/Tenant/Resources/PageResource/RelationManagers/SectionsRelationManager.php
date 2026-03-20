<?php

namespace App\Filament\Tenant\Resources\PageResource\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Блоки страницы';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description('Блок — часть страницы с заголовком и контентом (порядок задаётся в теме или при расширении схемы). Посетитель видит блоки сверху вниз.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок блока')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Коротко, по смыслу секции на странице.'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Блоков пока нет')
            ->emptyStateDescription('Добавьте блоки, чтобы собрать содержимое страницы по секциям.');
    }
}
