<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\TenantLocationResource\Pages;
use App\Models\TenantLocation;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Tenant\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
            ->components([
                Section::make('Локация')
                    ->description('Справочник точек доступности товара: город, выдача, фильтр на сайте.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label('Код в URL')
                            ->maxLength(191)
                            ->helperText('Латиница; если пусто — сгенерируется из названия. Используется в ?location= на сайте.'),
                        TextInput::make('city')->label('Город')->maxLength(120),
                        TextInput::make('region')->label('Регион / область')->maxLength(120),
                        TextInput::make('country')->label('Страна')->maxLength(120),
                        TextInput::make('address')->label('Адрес')->maxLength(500),
                        TextInput::make('sort_order')
                            ->label('Сортировка')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Активна')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Название')->searchable()->sortable(),
                TextColumn::make('slug')->label('Slug')->searchable(),
                TextColumn::make('city')->label('Город')->placeholder('—'),
                TextColumn::make('region')->label('Регион')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label('Активна')->boolean(),
                TextColumn::make('sort_order')->label('Порядок')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
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
