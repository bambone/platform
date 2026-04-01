<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\RedirectResource\Pages;
use App\Models\Redirect;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static ?string $navigationLabel = 'Редиректы';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 20;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-right';

    protected static ?string $modelLabel = 'Редирект';

    protected static ?string $pluralModelLabel = 'Редиректы';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('from_url')
                            ->label('Откуда (старый URL)')
                            ->required()
                            ->maxLength(500)
                            ->placeholder('/old-page')
                            ->helperText('Путь без домена, например: /old-page или /category/product'),
                        TextInput::make('to_url')
                            ->label('Куда (новый URL)')
                            ->required()
                            ->maxLength(500)
                            ->placeholder('/new-page')
                            ->helperText('Полный URL или путь: /new-page или https://example.com/page'),
                        Select::make('http_code')
                            ->label('HTTP код')
                            ->options(Redirect::httpCodes())
                            ->default(301),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_url')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('to_url')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('http_code')->badge(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('id')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedirects::route('/'),
            'create' => Pages\CreateRedirect::route('/create'),
            'edit' => Pages\EditRedirect::route('/{record}/edit'),
        ];
    }
}
