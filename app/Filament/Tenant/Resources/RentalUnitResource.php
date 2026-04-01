<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Support\FilamentInlineMarkdown;
use App\Filament\Tenant\Concerns\ResolvesDomainTermLabels;
use App\Filament\Tenant\Resources\RentalUnitResource\Pages;
use App\Models\RentalUnit;
use App\Terminology\DomainTermKeys;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RentalUnitResource extends Resource
{
    use ResolvesDomainTermLabels;

    protected static ?string $model = RentalUnit::class;

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
            ->components([
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
                        Select::make('status')
                            ->label('Статус единицы')
                            ->options(RentalUnit::statuses())
                            ->default('active')
                            ->helperText('Активна — участвует в выдаче. На обслуживании — временно недоступна.'),
                    ]),
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
