<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\TenantDomainResource\Pages;
use App\Filament\Support\TenantDomainStatusCopy;
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
                Section::make('Клиент и адрес')
                    ->description('Домен определяет, по какому адресу открывается сайт клиента. Поддомен выдаётся платформой; свой домен подключается через DNS.')
                    ->schema([
                        Select::make('tenant_id')
                            ->label('Клиент')
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('host')
                            ->label('Доменное имя')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Например: rent.example.com')
                            ->helperText('Без протокола (без https://). Для поддомена платформы обычно: имя-клиента.ваш-домен.'),
                        Select::make('type')
                            ->label('Тип подключения')
                            ->options(TenantDomain::types())
                            ->required()
                            ->helperText('Поддомен — адрес вида client.platform.com. Кастомный — ваш собственный домен.'),
                        Toggle::make('is_primary')
                            ->label('Основной домен сайта')
                            ->helperText('Основной адрес, который считается «главным» для клиента. Обычно один.'),
                    ])->columns(2),

                Section::make('Проверка и сертификат')
                    ->description('Статусы обновляются при проверке DNS и выпуске SSL. Если что-то зависло — проверьте записи у регистратора и подождите распространения DNS.')
                    ->schema([
                        Select::make('verification_status')
                            ->label('Проверка владения доменом')
                            ->options(TenantDomainStatusCopy::verificationOptions())
                            ->searchable(false)
                            ->native(false)
                            ->helperText('Пока статус не «Подтверждён», добавьте DNS-записи у регистратора. Ниже в списке для каждой строки показана подсказка «что делать дальше».'),
                        Select::make('ssl_status')
                            ->label('SSL-сертификат')
                            ->options(TenantDomainStatusCopy::sslOptions())
                            ->searchable(false)
                            ->native(false)
                            ->helperText('После корректного DNS сертификат обычно выпускается автоматически в течение нескольких минут.'),
                        TextInput::make('dns_target')
                            ->label('Цель DNS (CNAME / A)')
                            ->maxLength(255)
                            ->placeholder('Например: cdn.platform.example.com')
                            ->helperText('Куда должна указывать запись у регистратора. Показываем для справки при настройке.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('host')
                    ->label('Домен')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tenant.name')
                    ->label('Клиент')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->formatStateUsing(fn (?string $state): string => $state ? (TenantDomain::types()[$state] ?? $state) : '—'),
                IconColumn::make('is_primary')
                    ->label('Основной')
                    ->boolean(),
                TextColumn::make('verification_status')
                    ->label('Проверка')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => TenantDomainStatusCopy::verificationLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'verified' => 'success',
                        'failed' => 'danger',
                        'pending', 'verifying' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (TenantDomain $record): string => TenantDomainStatusCopy::verificationNextStep(
                        $record->verification_status,
                        $record->dns_target,
                        $record->host
                    )),
                TextColumn::make('ssl_status')
                    ->label('SSL')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => TenantDomainStatusCopy::sslLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'error' => 'danger',
                        'issuing', 'pending' => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (TenantDomain $record): string => TenantDomainStatusCopy::sslNextStep($record->ssl_status)),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Удалить домены?')
                        ->modalDescription('Сайт клиента может перестать открываться по этим адресам. Проверьте, что остался хотя бы один рабочий домен.'),
                ]),
            ])
            ->emptyStateHeading('Домены ещё не добавлены')
            ->emptyStateDescription('У клиента должен быть хотя бы один адрес (часто поддомен), чтобы сайт открывался.')
            ->emptyStateIcon('heroicon-o-globe-alt');
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
