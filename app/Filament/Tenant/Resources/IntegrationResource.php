<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\IntegrationResource\Pages;
use App\Filament\Tenant\Resources\IntegrationResource\RelationManagers\LogsRelationManager;
use App\Models\Integration;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Livewire;
use UnitEnum;

class IntegrationResource extends Resource
{
    protected static ?string $model = Integration::class;

    protected static ?string $navigationLabel = 'Интеграции';

    protected static string|UnitEnum|null $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 20;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $modelLabel = 'Интеграция';

    protected static ?string $pluralModelLabel = 'Интеграции';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Подключение')
                    ->description('Связь с внешней системой (учёт парка, бронирования и т.д.).')
                    ->schema([
                        Select::make('type')
                            ->label('Тип интеграции')
                            ->options(Integration::types())
                            ->required()
                            ->native(false)
                            ->helperText('Определяет, какие поля и API используются.'),
                        TextInput::make('name')
                            ->label('Название для списка')
                            ->maxLength(255)
                            ->placeholder('Например: RentProg основной')
                            ->helperText('Удобное имя для вашей команды.'),
                        Toggle::make('is_enabled')
                            ->label('Включена')
                            ->default(false)
                            ->helperText('Пока выключено, синхронизация не выполняется.'),
                    ]),

                Section::make('Параметры API (для разработчика)')
                    ->description('Ключи, URL и секреты. Не передавайте посторонним. Неверные значения ломают обмен данными.')
                    ->schema([
                        KeyValue::make('config')
                            ->label('Настройки')
                            ->keyLabel('Параметр')
                            ->valueLabel('Значение')
                            ->addActionLabel('Добавить')
                            ->helperText('Типовые ключи зависят от интеграции (api_key, base_url и т.д.) — уточняйте у разработки.'),
                    ])
                    ->collapsible()
                    // Пустые значения — секция раскрыта; после заполнения и сохранения — свёрнута (удобнее обзор формы).
                    ->collapsed(fn (): bool => self::shouldCollapseApiParametersSection()),
            ]);
    }

    /**
     * Секция свёрнута только если в уже сохранённом config есть хотя бы одно непустое значение.
     *
     * @param  array<string, mixed>|null  $config
     */
    private static function integrationConfigHasFilledValues(?array $config): bool
    {
        if ($config === null || $config === []) {
            return false;
        }

        foreach ($config as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }

            if (! is_string($value) && $value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    private static function shouldCollapseApiParametersSection(): bool
    {
        $livewire = Livewire::current();

        if (! $livewire instanceof EditRecord) {
            return false;
        }

        $record = $livewire->getRecord();
        if (! $record instanceof Integration) {
            return false;
        }

        /** @var array<string, mixed>|null $config */
        $config = $record->getAttribute('config');

        return self::integrationConfigHasFilledValues($config);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Тип')
                    ->formatStateUsing(fn (?string $state): string => $state ? (Integration::types()[$state] ?? $state) : '')
                    ->badge(),
                TextColumn::make('display_name')
                    ->label('Название'),
                IconColumn::make('is_enabled')
                    ->label('Вкл.')
                    ->boolean(),
                TextColumn::make('logs_count')
                    ->counts('logs')
                    ->label('Записей в журнале'),
            ])
            ->defaultSort('id')
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading('Интеграций нет')
            ->emptyStateDescription('Подключите внешнюю систему, когда будете синхронизировать парк или бронирования.')
            ->emptyStateIcon('heroicon-o-puzzle-piece');
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
