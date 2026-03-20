<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\PlatformSettingResource\Pages;
use App\Models\PlatformSetting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

    protected static ?string $modelLabel = 'Настройка';

    protected static ?string $pluralModelLabel = 'Настройки платформы';

    protected static ?string $panel = 'platform';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->schema([
                    TextInput::make('key')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->disabledOn('edit'),
                    Textarea::make('value')->rows(4)->columnSpanFull(),
                    Select::make('type')
                        ->options([
                            'string' => 'Строка',
                            'integer' => 'Число',
                            'boolean' => 'Да/нет',
                            'json' => 'JSON',
                        ])
                        ->default('string')
                        ->required(),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->searchable()->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('value')->limit(40),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
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
