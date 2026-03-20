<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\TenantDomainResource\Pages;
use App\Models\TenantDomain;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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

class TenantDomainResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = TenantDomain::class;

    protected static ?string $navigationLabel = 'Домены клиентов';

    protected static ?string $modelLabel = 'Домен';

    protected static ?string $pluralModelLabel = 'Домены';

    protected static ?string $panel = 'platform';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->schema([
                    Select::make('tenant_id')
                        ->relationship('tenant', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('host')->required()->maxLength(255)->unique(ignoreRecord: true),
                    Select::make('type')->options(TenantDomain::types())->required(),
                    Toggle::make('is_primary')->label('Основной'),
                    TextInput::make('ssl_status')->maxLength(50),
                    TextInput::make('verification_status')->maxLength(50),
                    TextInput::make('dns_target')->maxLength(255),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('host')->searchable()->sortable(),
                TextColumn::make('tenant.name')->label('Клиент')->sortable(),
                TextColumn::make('type'),
                IconColumn::make('is_primary')->boolean(),
                TextColumn::make('verification_status'),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantDomains::route('/'),
            'create' => Pages\CreateTenantDomain::route('/create'),
            'edit' => Pages\EditTenantDomain::route('/{record}/edit'),
        ];
    }
}
