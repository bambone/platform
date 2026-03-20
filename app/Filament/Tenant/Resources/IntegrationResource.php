<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\IntegrationResource\Pages;
use App\Filament\Tenant\Resources\IntegrationResource\RelationManagers\LogsRelationManager;
use App\Models\Integration;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IntegrationResource extends Resource
{
    protected static ?string $model = Integration::class;

    protected static ?string $navigationLabel = 'Интеграции';

    protected static ?string $modelLabel = 'Интеграция';

    protected static ?string $pluralModelLabel = 'Интеграции';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('type')
                            ->label('Тип')
                            ->options(Integration::types())
                            ->required()
                            ->native(false),
                        TextInput::make('name')
                            ->label('Название')
                            ->maxLength(255)
                            ->placeholder('RentProg'),
                        Toggle::make('is_enabled')
                            ->label('Включена')
                            ->default(false),
                        KeyValue::make('config')
                            ->label('Настройки (API key, URL и т.д.)')
                            ->keyLabel('Ключ')
                            ->valueLabel('Значение')
                            ->addActionLabel('Добавить')
                            ->helperText('Пример: api_key, base_url'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('type')
                    ->formatStateUsing(fn (?string $state): string => $state ? (Integration::types()[$state] ?? $state) : '')
                    ->badge(),
                TextColumn::make('display_name')->label('Название'),
                IconColumn::make('is_enabled')->boolean()->label('Вкл.'),
                TextColumn::make('logs_count')->counts('logs')->label('Логов'),
            ])
            ->defaultSort('id')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            LogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIntegrations::route('/'),
            'create' => Pages\CreateIntegration::route('/create'),
            'edit' => Pages\EditIntegration::route('/{record}/edit'),
        ];
    }
}
