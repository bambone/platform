<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\DomainLocalizationPresetResource\Pages;
use App\Filament\Platform\Resources\DomainLocalizationPresetResource\RelationManagers\PresetTermsRelationManager;
use App\Models\DomainLocalizationPreset;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DomainLocalizationPresetResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = DomainLocalizationPreset::class;

    protected static ?string $navigationLabel = 'Пресеты терминологии';

    protected static ?string $modelLabel = 'Пресет терминологии';

    protected static ?string $pluralModelLabel = 'Пресеты терминологии';

    protected static ?string $panel = 'platform';

    protected static string|\UnitEnum|null $navigationGroup = 'Система';

    protected static ?int $navigationSort = 26;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('sort_order')->orderBy('name');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Пресет')
                    ->description('Slug используется в данных и миграциях; меняйте осознанно.')
                    ->schema([
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit')
                            ->helperText('Например: moto_rental. После создания не редактируют.'),
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Описание')
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('sort_order')
                            ->label('Порядок в списках')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Акт.')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
                TextColumn::make('tenants_count')
                    ->label('Клиентов')
                    ->counts('tenants'),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Удалить пресеты?')
                        ->modalDescription('Клиенты с этим пресетом потеряют привязку (FK nullOnDelete). Лучше отключить пресет флагом is_active.'),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            PresetTermsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDomainLocalizationPresets::route('/'),
            'create' => Pages\CreateDomainLocalizationPreset::route('/create'),
            'edit' => Pages\EditDomainLocalizationPreset::route('/{record}/edit'),
        ];
    }
}
