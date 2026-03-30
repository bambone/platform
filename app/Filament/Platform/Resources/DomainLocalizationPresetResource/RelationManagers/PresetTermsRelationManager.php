<?php

namespace App\Filament\Platform\Resources\DomainLocalizationPresetResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PresetTermsRelationManager extends RelationManager
{
    protected static string $relationship = 'presetTerms';

    protected static ?string $title = 'Подписи в этом пресете';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Значение')
                    ->schema([
                        Select::make('term_id')
                            ->label('Системный термин')
                            ->relationship('term', 'term_key', modifyQueryUsing: fn ($q) => $q->orderBy('term_key'))
                            ->required()
                            ->disabledOn('edit')
                            ->preload()
                            ->helperText('При создании выберите термин; после сохранения ключ меняют только через удаление строки и новую.'),
                        TextInput::make('label')
                            ->label('Подпись')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('short_label')
                            ->label('Короткая подпись')
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->label('Заметки')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('term'))
            ->columns([
                TextColumn::make('term.term_key')
                    ->label('Ключ')
                    ->searchable(),
                TextColumn::make('term.group')
                    ->label('Группа'),
                TextColumn::make('label')
                    ->label('Подпись')
                    ->wrap(),
                TextColumn::make('short_label')
                    ->label('Коротко')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('term_id');
    }
}
