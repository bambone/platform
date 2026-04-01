<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Support\TenantDomainStatusCopy;
use App\Filament\Tenant\Resources\CustomDomainResource\Pages;
use App\Models\TenantDomain;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CustomDomainResource extends Resource
{
    protected static ?string $model = TenantDomain::class;

    protected static ?string $navigationLabel = 'Свой домен';

    protected static ?string $modelLabel = 'Домен';

    protected static ?string $pluralModelLabel = 'Свои домены';

    protected static string|UnitEnum|null $navigationGroup = 'Infrastructure';

    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    public static function getEloquentQuery(): Builder
    {
        $tenant = currentTenant();
        $query = parent::getEloquentQuery();

        if ($tenant === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('tenant_id', $tenant->id)
            ->where('type', TenantDomain::TYPE_CUSTOM);
    }

    public static function canViewAny(): bool
    {
        return Auth::check() && currentTenant() !== null;
    }

    public static function canCreate(): bool
    {
        return Auth::check() && currentTenant() !== null;
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof TenantDomain
            && $record->type === TenantDomain::TYPE_CUSTOM
            && currentTenant() !== null
            && (int) $record->tenant_id === (int) currentTenant()->id;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Подключение домена')
                    ->schema([
                        TextInput::make('host')
                            ->label('Доменное имя')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('example.com')
                            ->helperText('Без https://. После сохранения настройте DNS по инструкции ниже.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('host')
                    ->label('Домен')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => TenantDomainStatusCopy::statusLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        TenantDomain::STATUS_ACTIVE => 'success',
                        TenantDomain::STATUS_FAILED => 'danger',
                        TenantDomain::STATUS_VERIFYING, TenantDomain::STATUS_PENDING => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('ssl_status')
                    ->label('SSL')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => TenantDomainStatusCopy::sslLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        TenantDomain::SSL_ISSUED, TenantDomain::SSL_NOT_REQUIRED => 'success',
                        TenantDomain::SSL_FAILED => 'danger',
                        TenantDomain::SSL_PENDING => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('last_checked_at')
                    ->label('Проверка DNS')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('activated_at')
                    ->label('Активирован')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->emptyStateHeading('Свой домен не подключён')
            ->emptyStateDescription('Добавьте домен и выполните проверку DNS, затем нажмите «Проверить и подключить».');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomDomains::route('/'),
            'create' => Pages\CreateCustomDomain::route('/create'),
            'edit' => Pages\EditCustomDomain::route('/{record}/edit'),
        ];
    }
}
