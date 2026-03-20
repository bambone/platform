<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\TenantResource\Pages;
use App\Models\TemplatePreset;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = Tenant::class;

    protected static ?string $modelLabel = 'Клиент';

    protected static ?string $pluralModelLabel = 'Клиенты';

    protected static ?string $panel = 'platform';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('slug')->required()->unique(ignoreRecord: true)->maxLength(255),
                        TextInput::make('brand_name')->maxLength(255),
                        Select::make('status')
                            ->options(Tenant::statuses())
                            ->default('trial')
                            ->required(),
                        Select::make('plan_id')
                            ->relationship('plan', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('template_preset_id')
                            ->label('Шаблон сайта')
                            ->options(TemplatePreset::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                    ])->columns(2),
                Section::make('Локализация')
                    ->schema([
                        TextInput::make('timezone')->default('Europe/Moscow')->maxLength(50),
                        TextInput::make('locale')->default('ru')->maxLength(10),
                        TextInput::make('country')->maxLength(2),
                        TextInput::make('currency')->default('RUB')->maxLength(3),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('plan.name'),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
