<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = Plan::class;

    protected static ?string $navigationLabel = 'Тарифы';

    protected static ?string $modelLabel = 'Тариф';

    protected static ?string $pluralModelLabel = 'Тарифы';

    protected static ?string $panel = 'platform';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
                    KeyValue::make('limits_json')->label('Лимиты')->columnSpanFull(),
                    TagsInput::make('features_json')->label('Функции (теги)')->placeholder('feature_key')->columnSpanFull(),
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
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
