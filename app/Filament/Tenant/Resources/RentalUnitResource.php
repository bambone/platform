<?php

namespace App\Filament\Tenant\Resources;

use App\Enums\MotorcycleLocationMode;
use App\Filament\Support\FilamentInlineMarkdown;
use App\Filament\Tenant\Concerns\ResolvesDomainTermLabels;
use App\Filament\Tenant\Forms\LinkedBookableSchedulingForm;
use App\Filament\Tenant\Resources\RentalUnitResource\Pages;
use App\Models\Motorcycle;
use App\Models\RentalUnit;
use App\Models\TenantLocation;
use App\Terminology\DomainTermKeys;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use App\Filament\Tenant\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RentalUnitResource extends Resource
{
    use ResolvesDomainTermLabels;

    protected static ?string $model = RentalUnit::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 20;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    public static function getNavigationLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::FLEET_UNIT_PLURAL, 'Единицы парка');
    }

    public static function getModelLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::FLEET_UNIT, 'Единица парка');
    }

    public static function getPluralModelLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::FLEET_UNIT_PLURAL, 'Единицы парка');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Единица парка')
                    ->persistTabInQueryString(LinkedBookableSchedulingForm::RENTAL_UNIT_TAB_QUERY_KEY)
                    ->tabs([
                        'main' => Tab::make('Основное')
                            ->id(LinkedBookableSchedulingForm::TAB_KEY_MAIN)
                            ->schema([
                                Section::make('Связь с каталогом и внешними системами')
                                    ->description(FilamentInlineMarkdown::toHtml(
                                        '**Единица парка** — конкретный экземпляр техники (например с номером или VIN), привязанный к **карточке в каталоге**. На сайте посетитель обычно видит карточку; единица парка нужна для учёта доступности и бронирований.'
                                    ))
                                    ->schema([
                                        Select::make('motorcycle_id')
                                            ->label('Карточка в каталоге')
                                            ->relationship('motorcycle', 'name')
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->helperText('Модель/карточка, к которой относится эта физическая единица.'),
                                        Select::make('integration_id')
                                            ->label('Интеграция')
                                            ->relationship('integration', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Без внешней системы')
                                            ->helperText('Если учёт ведётся во внешней программе (например RentProg).'),
                                        TextInput::make('external_id')
                                            ->label('ID во внешней системе')
                                            ->maxLength(255)
                                            ->placeholder('Например: ID в RentProg')
                                            ->helperText('Заполняется, когда единица синхронизируется с интеграцией.'),
                                        TextInput::make('unit_label')
                                            ->label('Внутренняя метка')
                                            ->maxLength(255)
                                            ->helperText('Краткое имя для админки и экспорта (не обязательно уникально).'),
                                        Textarea::make('notes')
                                            ->label('Заметка')
                                            ->rows(2)
                                            ->maxLength(2000)
                                            ->columnSpanFull(),
                                        CheckboxList::make('tenant_location_ids')
                                            ->label('Локации этой единицы')
                                            ->options(fn (): array => TenantLocation::query()
                                                ->where('is_active', true)
                                                ->orderBy('sort_order')
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->all())
                                            ->visible(function (Get $get, ?RentalUnit $record): bool {
                                                $motorcycleId = $get('motorcycle_id') ?? $record?->motorcycle_id;
                                                if ($motorcycleId === null) {
                                                    return false;
                                                }
                                                $motorcycle = Motorcycle::query()->find($motorcycleId);

                                                return $motorcycle !== null
                                                    && $motorcycle->uses_fleet_units
                                                    && ($motorcycle->location_mode ?? null) === MotorcycleLocationMode::PerUnit;
                                            })
                                            ->dehydrated(function (Get $get, ?RentalUnit $record): bool {
                                                $motorcycleId = $get('motorcycle_id') ?? $record?->motorcycle_id;
                                                if ($motorcycleId === null) {
                                                    return false;
                                                }
                                                $motorcycle = Motorcycle::query()->find($motorcycleId);

                                                return $motorcycle !== null
                                                    && $motorcycle->uses_fleet_units
                                                    && ($motorcycle->location_mode ?? null) === MotorcycleLocationMode::PerUnit;
                                            })
                                            ->columns(2)
                                            ->helperText('Только если на карточке включены единицы парка и режим «локации по каждой единице».'),
                                        Select::make('status')
                                            ->label('Статус единицы')
                                            ->options(RentalUnit::statuses())
                                            ->default('active')
                                            ->helperText('Активна — участвует в выдаче. На обслуживании — временно недоступна.'),
                                    ]),
                                LinkedBookableSchedulingForm::rentalUnitCreateNotice(),
                            ]),
                        LinkedBookableSchedulingForm::TAB_KEY_ONLINE_BOOKING => LinkedBookableSchedulingForm::rentalUnitOnlineBookingTab(),
                    ])
                    ->columnSpan(['default' => 12, 'lg' => 12]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('motorcycle.name')
                    ->label('Карточка каталога')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('integration.display_name')
                    ->label('Интеграция')
                    ->placeholder('—'),
                TextColumn::make('external_id')
                    ->label('Внешний ID')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (RentalUnit::statuses()[$state] ?? $state) : '')
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'maintenance' => 'warning',
                        'inactive' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('id')
            ->actions([
                EditAction::make(),
            ])
            ->emptyStateHeading('Единиц парка пока нет')
            ->emptyStateDescription('Добавьте единицу, когда нужно отличать конкретные мотоциклы внутри одной карточки каталога.')
            ->emptyStateIcon('heroicon-o-cube');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRentalUnits::route('/'),
            'create' => Pages\CreateRentalUnit::route('/create'),
            'edit' => Pages\EditRentalUnit::route('/{record}/edit'),
        ];
    }
}
