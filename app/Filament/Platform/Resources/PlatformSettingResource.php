<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\PlatformSettingResource\Pages;
use App\Filament\Support\PlatformSettingRegistry;
use App\Models\PlatformSetting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlatformSettingResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = PlatformSetting::class;

    protected static ?string $navigationLabel = 'Настройки платформы';

    protected static ?string $modelLabel = 'Параметр';

    protected static ?string $pluralModelLabel = 'Настройки платформы';

    protected static ?string $panel = 'platform';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Что вы настраиваете')
                    ->description('Предпочтительно выбирайте параметр из списка — так проще не ошибиться. Произвольный ключ нужен только для интеграций и доработок под конкретный проект.')
                    ->schema([
                        Toggle::make('use_custom_key')
                            ->label('Произвольный системный ключ (для разработчика)')
                            ->helperText('Включайте только если вам явно передали имя ключа. Неверное имя может привести к тому, что приложение не увидит настройку.')
                            ->default(false)
                            ->live()
                            ->visibleOn('create'),
                        Select::make('registry_key')
                            ->label('Параметр из списка')
                            ->required(fn ($get): bool => ! (bool) $get('use_custom_key'))
                            ->options(function (): array {
                                $existing = PlatformSetting::query()->pluck('key')->all();

                                return collect(PlatformSettingRegistry::knownKeys())
                                    ->reject(fn (string $k): bool => in_array($k, $existing, true))
                                    ->mapWithKeys(fn (string $k): array => [$k => PlatformSettingRegistry::label($k)])
                                    ->all();
                            })
                            ->searchable()
                            ->live()
                            ->visible(fn ($get): bool => ! $get('use_custom_key'))
                            ->visibleOn('create')
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $def = PlatformSettingRegistry::definition($state);
                                if ($def !== null) {
                                    $set('type', $def['type']);
                                }
                            })
                            ->dehydrated(false)
                            ->helperText('Ключи из списка снабжены пояснениями ниже.'),
                        TextInput::make('key')
                            ->label('Системный ключ')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->required(fn ($get, string $operation): bool => $operation === 'create' && (bool) $get('use_custom_key'))
                            ->visible(fn ($get, string $operation): bool => $operation === 'edit' || ((bool) $get('use_custom_key') && $operation === 'create'))
                            ->helperText(fn (string $operation): string => $operation === 'edit'
                                ? 'Ключ после создания не меняется. Для другого ключа создайте новую запись.'
                                : 'Латиница, точки и подчёркивания. Согласуйте имя с разработкой.'),
                    ]),

                Section::make('Значение')
                    ->description(function ($get, string $operation): ?string {
                        if ($operation !== 'edit') {
                            return null;
                        }
                        $def = PlatformSettingRegistry::definition($get('key'));

                        return $def['description'] ?? null;
                    })
                    ->schema([
                        Select::make('type')
                            ->label('Тип значения')
                            ->options([
                                'string' => 'Текст',
                                'integer' => 'Целое число',
                                'boolean' => 'Да / нет',
                                'json' => 'JSON (структура)',
                            ])
                            ->default('string')
                            ->required()
                            ->live()
                            ->disabled(fn ($get, string $operation): bool => $operation === 'edit' && PlatformSettingRegistry::definition($get('key')) !== null)
                            ->helperText('Определяет, как значение сохраняется и читается приложением.'),
                        Textarea::make('value')
                            ->label('Значение')
                            ->rows(fn ($get): int => $get('type') === 'json' ? 8 : 4)
                            ->required(fn ($get): bool => in_array($get('type'), ['string', 'json', 'integer'], true))
                            ->visible(fn ($get): bool => $get('type') !== 'boolean')
                            ->helperText(fn ($get): ?string => match ($get('type')) {
                                'json' => 'Корректный JSON. Ошибка формата может сломать часть функционала.',
                                'integer' => 'Только целое число, без пробелов и букв.',
                                default => 'Текст, который будет использовать приложение для этого параметра.',
                            }),
                        Toggle::make('value_boolean')
                            ->label('Включено')
                            ->visible(fn ($get): bool => $get('type') === 'boolean')
                            ->dehydrated(false),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Параметр')
                    ->description(fn (PlatformSetting $record): string => PlatformSettingRegistry::group($record->key))
                    ->formatStateUsing(fn (string $state): string => PlatformSettingRegistry::label($state))
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'string' => 'Текст',
                        'integer' => 'Число',
                        'boolean' => 'Да/нет',
                        'json' => 'JSON',
                        default => $state ?? '—',
                    }),
                TextColumn::make('value')
                    ->label('Значение')
                    ->limit(50)
                    ->tooltip(fn (PlatformSetting $record): ?string => strlen((string) $record->value) > 50 ? (string) $record->value : null),
            ])
            ->defaultSort('key')
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Удалить выбранные параметры?')
                        ->modalDescription('Приложение перестанет получать эти значения из настроек платформы. Убедитесь, что ключи не нужны интеграциям.'),
                ]),
            ])
            ->emptyStateHeading('Параметры ещё не заданы')
            ->emptyStateDescription('Добавьте настройки из списка или (для разработчика) произвольный ключ. Типичные параметры: название платформы, email поддержки.')
            ->emptyStateIcon('heroicon-o-cog-6-tooth');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformSettings::route('/'),
            'create' => Pages\CreatePlatformSetting::route('/create'),
            'edit' => Pages\EditPlatformSetting::route('/{record}/edit'),
        ];
    }
}
