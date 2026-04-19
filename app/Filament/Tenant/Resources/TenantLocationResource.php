<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Filament\Shared\Lifecycle\AdminFilamentDelete;
use App\Filament\Tenant\Resources\TenantLocationResource\Pages;
use App\Geocoding\GeocodePlacesService;
use App\Models\TenantLocation;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class TenantLocationResource extends Resource
{
    protected static ?string $model = TenantLocation::class;

    protected static ?string $navigationLabel = 'Локации';

    protected static string|UnitEnum|null $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 25;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $modelLabel = 'Локация';

    protected static ?string $pluralModelLabel = 'Локации';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'xl' => 12])
                    ->schema([
                        Section::make('Основное')
                            ->description('Название и география: что видят посетители и как участвует точка в фильтрах.')
                            ->schema([
                                Hidden::make('_geo_lock_region')->default(false)->dehydrated(false),
                                Hidden::make('_geo_lock_country')->default(false)->dehydrated(false),
                                Hidden::make('_last_geocode_pick')->default('')->dehydrated(false),
                                Hidden::make('_geocode_applying')->default(false)->dehydrated(false),
                                TextInput::make('name')
                                    ->label('Название')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull()
                                    ->helperText('Как локация отображается в админке и в списках выбора.')
                                    ->live(debounce: 400)
                                    ->afterStateUpdated(function (Set $set, ?string $state, Get $get): void {
                                        if (blank($state) || filled($get('slug'))) {
                                            return;
                                        }
                                        $set('slug', Str::slug($state) ?: '');
                                    }),
                                TextInput::make('city')
                                    ->label('Город')
                                    ->maxLength(120)
                                    ->columnSpanFull()
                                    ->helperText('Основной географический признак для сайта и фильтров по локации. Можно ввести вручную или подставить из подсказки ниже (OpenStreetMap / Nominatim).'),
                                Select::make('_geocode_pick')
                                    ->label('Подсказка по городу')
                                    ->helperText('Начните вводить название и выберите вариант — подставятся город, регион и страна. Данные с сервера RentBase, не из браузера напрямую в OSM.')
                                    ->placeholder('Начните вводить, например Челябинск…')
                                    ->searchable()
                                    ->searchDebounce(500)
                                    ->native(false)
                                    ->options([])
                                    ->getSearchResultsUsing(
                                        function (?string $search, GeocodePlacesService $geocoder): array {
                                            if ($search === null || $search === '') {
                                                return [];
                                            }

                                            return $geocoder->searchOptions($search);
                                        },
                                    )
                                    ->getOptionLabelUsing(
                                        function (?string $value, GeocodePlacesService $geocoder): ?string {
                                            if ($value === null || $value === '') {
                                                return null;
                                            }

                                            return $geocoder->resolvePick($value)?->displayLabel ?? $value;
                                        },
                                    )
                                    ->dehydrated(false)
                                    ->live()
                                    ->afterStateUpdated(
                                        function (?string $state, Set $set, Get $get, GeocodePlacesService $geocoder): void {
                                            if ($state === null || $state === '') {
                                                return;
                                            }
                                            $dto = $geocoder->resolvePick($state);
                                            if ($dto === null) {
                                                return;
                                            }

                                            $priorPick = (string) ($get('_last_geocode_pick') ?? '');
                                            $samePick = $state === $priorPick;

                                            if (! $samePick) {
                                                $set('_last_geocode_pick', $state);
                                                $set('_geo_lock_region', false);
                                                $set('_geo_lock_country', false);
                                            }

                                            $set('_geocode_applying', true);
                                            $set('city', $dto->city);

                                            if (! $samePick || ! $get('_geo_lock_region')) {
                                                $set('region', $dto->region);
                                            }
                                            if (! $samePick || ! $get('_geo_lock_country')) {
                                                $set('country', $dto->country);
                                            }
                                            $set('_geocode_applying', false);
                                        },
                                    )
                                    ->columnSpanFull(),
                                TextInput::make('region')
                                    ->label('Регион / область')
                                    ->maxLength(120)
                                    ->helperText('Уточнение области или региона; подписи и внутренний учёт.')
                                    ->live()
                                    ->afterStateUpdated(function (mixed $state, mixed $old, Set $set, Get $get): void {
                                        if ($get('_geocode_applying')) {
                                            return;
                                        }
                                        $newS = is_string($state) ? $state : (string) $state;
                                        $oldS = is_string($old) ? $old : (string) ($old ?? '');
                                        if ($newS === $oldS) {
                                            return;
                                        }
                                        $set('_geo_lock_region', true);
                                    }),
                                TextInput::make('country')
                                    ->label('Страна')
                                    ->maxLength(120)
                                    ->helperText('Полезно, если в проекте несколько стран.')
                                    ->live()
                                    ->afterStateUpdated(function (mixed $state, mixed $old, Set $set, Get $get): void {
                                        if ($get('_geocode_applying')) {
                                            return;
                                        }
                                        $newS = is_string($state) ? $state : (string) $state;
                                        $oldS = is_string($old) ? $old : (string) ($old ?? '');
                                        if ($newS === $oldS) {
                                            return;
                                        }
                                        $set('_geo_lock_country', true);
                                    }),
                                TextInput::make('address')
                                    ->label('Адрес')
                                    ->maxLength(500)
                                    ->columnSpanFull()
                                    ->helperText('Точка выдачи или офис — по желанию, для команды и уточнений на сайте.'),
                            ])
                            ->columns(2)
                            ->columnSpan(['default' => 1, 'xl' => 8]),
                        Section::make('Ссылка, статус и публикация')
                            ->description('Код в URL, порядок в списках и участие локации в выдаче на сайте.')
                            ->schema([
                                TextInput::make('slug')
                                    ->label('Код в URL')
                                    ->maxLength(191)
                                    ->suffixAction(
                                        Action::make('regenerateTenantLocationSlug')
                                            ->label('Пересобрать из названия')
                                            ->hiddenLabel()
                                            ->icon('heroicon-m-arrow-path')
                                            ->color('gray')
                                            ->tooltip('Пересобрать код из поля «Название»')
                                            ->disabled(fn (Get $get): bool => blank($get('name')))
                                            ->action(function (Set $set, Get $get): void {
                                                $name = $get('name');
                                                if (blank($name)) {
                                                    return;
                                                }
                                                $set('slug', Str::slug((string) $name) ?: '');
                                            }),
                                    )
                                    ->helperText('Латиница, цифры и дефис. Если оставить пустым, код будет создан из названия. Кнопка справа принудительно пересобирает код из «Название». Используется в ссылках и в фильтре локации на сайте.'),
                                TextInput::make('sort_order')
                                    ->label('Порядок в списках')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Меньше значение — выше в списках выбора локации.'),
                                Toggle::make('is_active')
                                    ->label('Показывать на сайте')
                                    ->helperText('Статус записи: неактивные локации не участвуют в выдаче и фильтрах.')
                                    ->default(true)
                                    ->inline(false),
                            ])
                            ->columns(1)
                            ->columnSpan(['default' => 1, 'xl' => 4]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Название')->searchable()->sortable(),
                TextColumn::make('slug')->label('Код в URL')->searchable(),
                TextColumn::make('city')->label('Город')->placeholder('—'),
                TextColumn::make('region')->label('Регион')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label('Активна')->boolean(),
                TextColumn::make('sort_order')->label('Порядок')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                AdminFilamentDelete::configureTableDeleteAction(
                    DeleteAction::make(),
                    ['entry' => 'filament.tenant.tenant_location.table'],
                ),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantLocations::route('/'),
            'create' => Pages\CreateTenantLocation::route('/create'),
            'edit' => Pages\EditTenantLocation::route('/{record}/edit'),
        ];
    }
}
