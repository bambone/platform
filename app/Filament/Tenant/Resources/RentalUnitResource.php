<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\RentalUnitResource\Pages;
use App\Models\RentalUnit;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RentalUnitResource extends Resource
{
    protected static ?string $model = RentalUnit::class;

    protected static ?string $navigationLabel = 'Арендные единицы';

    protected static ?string $modelLabel = 'Арендная единица';

    protected static ?string $pluralModelLabel = 'Арендные единицы';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('motorcycle_id')
                            ->relationship('motorcycle', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('integration_id')
                            ->relationship('integration', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Без интеграции'),
                        TextInput::make('external_id')
                            ->label('Внешний ID')
                            ->maxLength(255)
                            ->placeholder('ID в RentProg'),
                        Select::make('status')
                            ->options(RentalUnit::statuses())
                            ->default('active'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('motorcycle.name')->searchable()->sortable(),
                TextColumn::make('integration.display_name')->label('Интеграция')->placeholder('—'),
                TextColumn::make('external_id')->placeholder('—'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (?string $state): string => $state ? (RentalUnit::statuses()[$state] ?? $state) : ''),
            ])
            ->defaultSort('id')
            ->actions([
                EditAction::make(),
            ]);
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
