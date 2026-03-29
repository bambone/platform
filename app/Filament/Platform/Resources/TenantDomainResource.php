<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\TenantDomainResource\Pages;
use App\Filament\Support\FilamentInlineMarkdown;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

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
                    ->description(FilamentInlineMarkdown::toHtml(
                        'Сайт открывается по любому хосту со статусом **«Активен»** (настраивается справа). **Тип подключения** не «включает» сайт — он задаёт, какие поля нужны (поддомен платформы или свой домен). Подробная таблица полей — в `docs/SETUP_ADMIN.md`.'
                    ))
                    ->schema([
                        Select::make('tenant_id')
                            ->label('Клиент')
                            ->relationship('tenant', 'name')
                            ->preload()
                            ->required(),
                        TextInput::make('host')
                            ->label('Доменное имя')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Например: motolevins.rentbase.su')
                            ->helperText(FilamentInlineMarkdown::toHtml(
                                'Без префикса `https://`. Поддомен: `slug.корень` платформы; кастомный — полное имя у регистратора.'
                            )),
                        Select::make('type')
                            ->label('Тип подключения')
                            ->options(TenantDomain::types())
                            ->required()
                            ->live()
                            ->placeholder('Выберите тип подключения')
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state === TenantDomain::TYPE_SUBDOMAIN) {
                                    $set('ssl_status', TenantDomain::SSL_NOT_REQUIRED);
                                    $set('dns_target', '');
                                    $set('status', TenantDomain::STATUS_ACTIVE);
                                }
                            })
                            ->helperText(FilamentInlineMarkdown::toHtml(
                                '**Поддомен** — зона платформы; **кастомный** — справа появляются DNS и SSL.'
                            )),
                        Toggle::make('is_primary')
                            ->label('Основной домен сайта')
                            ->helperText(FilamentInlineMarkdown::toHtml(
                                'Канонический URL для подсказок и дефолтов. **Все активные** домены всё равно открывают сайт; на проде основным лучше указать боевой хост.'
                            )),
                    ])->columns(2),

                Section::make('Проверка и сертификат')
                    ->afterHeader(
                        SchemaView::make('filament.platform.components.tenant-domain-type-loading')
                    )
                    ->description(function (Get $get): Htmlable {
                        if (blank($get('type'))) {
                            return FilamentInlineMarkdown::toHtml(
                                'Сначала выберите **тип подключения** слева — тогда здесь можно менять статус, SSL и DNS.'
                            );
                        }

                        return FilamentInlineMarkdown::toHtml(
                            '**Активен** — хост привязан к клиенту. **SSL** и **DNS** в основном для кастомного домена; кеш резолвера после сохранения сбрасывается сам.'
                        );
                    })
                    ->schema([
                        Select::make('status')
                            ->label('Статус домена')
                            ->options(TenantDomainStatusCopy::statusOptions())
                            ->default(TenantDomain::STATUS_PENDING)
                            ->required()
                            ->disabled(fn (Get $get): bool => blank($get('type')))
                            ->dehydrated(fn (Get $get): bool => filled($get('type')))
                            ->helperText(FilamentInlineMarkdown::toHtml(
                                'Только **Активен** — публичный сайт и `/admin` по этому хосту.'
                            )),
                        Select::make('ssl_status')
                            ->label('SSL-сертификат')
                            ->options(TenantDomainStatusCopy::sslOptions())
                            ->disabled(fn (Get $get): bool => blank($get('type')) || $get('type') === TenantDomain::TYPE_SUBDOMAIN)
                            ->dehydrated()
                            ->helperText(fn (Get $get): Htmlable => $get('type') === TenantDomain::TYPE_SUBDOMAIN
                                ? FilamentInlineMarkdown::toHtml('Для поддомена — **«Не требуется»**; TLS обычно на сервере.')
                                : FilamentInlineMarkdown::toHtml('После корректного DNS сертификат выпускается процессом платформы.')),
                        TextInput::make('dns_target')
                            ->label('Цель DNS (CNAME / A)')
                            ->maxLength(255)
                            ->placeholder('Хост платформы для CNAME, напр. domains.rentbase.su')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip(function (Get $get): string {
                                if (blank($get('type'))) {
                                    return 'Сначала выберите тип подключения слева. Это поле нужно только для кастомного домена.';
                                }

                                if ($get('type') !== TenantDomain::TYPE_CUSTOM) {
                                    return 'Для поддомена платформы DNS на стороне регистратора к этому значению не привязывают — поле не используется.';
                                }

                                return 'Куда смотрит запись у регистратора: здесь указывается не «ваш домен» (он уже в поле «Доменное имя» слева), а целевой хост или IP платформы, на который владелец домена создаёт CNAME или A в панели регистратора. Обычно это значение из инструкции платформы. В карточке домена оно хранится как текст-подсказка для клиента; запись в DNS делает владелец домена, а не поле само по себе.';
                            })
                            ->disabled(fn (Get $get): bool => blank($get('type')) || $get('type') === TenantDomain::TYPE_SUBDOMAIN)
                            ->dehydrated(fn (Get $get): bool => $get('type') === TenantDomain::TYPE_CUSTOM)
                            ->helperText(fn (Get $get): Htmlable => $get('type') === TenantDomain::TYPE_CUSTOM
                                ? FilamentInlineMarkdown::toHtml(
                                    '**Не дублируйте свой домен.** Сюда — **куда** у регистратора должна указывать запись (хост/IP платформы). Сайт как `https://…` задаётся полем **«Доменное имя»** слева.'
                                )
                                : FilamentInlineMarkdown::toHtml('Только для **кастомного** домена; при поддомене поле отключено.')),
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
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => TenantDomainStatusCopy::statusLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        TenantDomain::STATUS_ACTIVE => 'success',
                        TenantDomain::STATUS_FAILED => 'danger',
                        TenantDomain::STATUS_PENDING, TenantDomain::STATUS_VERIFYING => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (TenantDomain $record): string => TenantDomainStatusCopy::statusNextStep(
                        $record->status,
                        $record->dns_target,
                        $record->host
                    )),
                TextColumn::make('ssl_status')
                    ->label('SSL')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => TenantDomainStatusCopy::sslLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        TenantDomain::SSL_ISSUED, TenantDomain::SSL_NOT_REQUIRED => 'success',
                        TenantDomain::SSL_FAILED => 'danger',
                        TenantDomain::SSL_PENDING => 'warning',
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
