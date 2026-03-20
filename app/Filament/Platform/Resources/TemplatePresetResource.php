<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\TemplatePresetResource\Pages;
use App\Models\TemplatePreset;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TemplatePresetResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = TemplatePreset::class;

    protected static ?string $navigationLabel = 'Шаблоны сайтов';

    protected static ?string $modelLabel = 'Шаблон';

    protected static ?string $pluralModelLabel = 'Шаблоны';

    protected static ?string $panel = 'platform';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
                    Textarea::make('description')->rows(3)->columnSpanFull(),
                    KeyValue::make('config_json')->label('Конфиг')->columnSpanFull(),
                    TextInput::make('sort_order')->numeric()->default(0),
                    Toggle::make('is_active')->default(true),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTemplatePresets::route('/'),
            'create' => Pages\CreateTemplatePreset::route('/create'),
            'edit' => Pages\EditTemplatePreset::route('/{record}/edit'),
        ];
    }
}
