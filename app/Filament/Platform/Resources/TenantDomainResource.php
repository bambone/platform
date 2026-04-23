<?php

namespace App\Filament\Platform\Resources;

use App\Admin\Lifecycle\AdminDeleteExecutor;
use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\TenantDomainResource\Pages;
use App\Filament\Shared\Lifecycle\AdminFilamentDelete;
use App\Filament\Support\FilamentInlineMarkdown;
use App\Filament\Support\TenantDomainStatusCopy;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Rules\TenantDomainHostRule;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Js;
use Illuminate\Support\LazyCollection;

class TenantDomainResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = TenantDomain::class;

    protected static ?string $navigationLabel = 'Домены клиентов';

    protected static ?string $modelLabel = 'Домен';

    protected static ?string $pluralModelLabel = 'Домены';

    protected static ?string $panel = 'platform';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('tenant');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Клиент и адрес')
                    ->description(FilamentInlineMarkdown::toHtml(
                        'Сайт открывается по любому хосту со статусом **«Активен»** (настраивается справа). **Тип подключения** не «включает» сайт — он задаёт, какие поля нужны (поддомен платформы или свой домен). Таблица полей домена — в `docs/operations/setup-access-deploy.md#domain-fields-table`.'
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
                            ->placeholder('example.com')
                            ->rules([new TenantDomainHostRule])
                            ->helperText(FilamentInlineMarkdown::toHtml(
                                'Укажите домен **без** `http://` или `https://`, **без** пути и пробелов. Пример: `tenant.example.com` или поддомен платформы `slug.корень`.'
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ))->wrap(),
                TextColumn::make('ssl_status')
                    ->label('SSL-сертификат')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => TenantDomainStatusCopy::sslLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        TenantDomain::SSL_ISSUED, TenantDomain::SSL_NOT_REQUIRED => 'success',
                        TenantDomain::SSL_FAILED => 'danger',
                        TenantDomain::SSL_PENDING => 'warning',
                        default => 'gray',
                    })
                    ->description(fn (TenantDomain $record): string => TenantDomainStatusCopy::sslNextStep($record->ssl_status))->wrap(),
            ])
            ->defaultSort('host')
            ->groups([
                Group::make('tenant_id')
                    ->label('Клиент')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn (TenantDomain $record): string => $record->tenant?->name ?? '—')
                    ->orderQueryUsing(function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            Tenant::query()
                                ->select('name')
                                ->whereColumn('tenants.id', 'tenant_domains.tenant_id')
                                ->limit(1),
                            $direction
                        );
                    }),
            ])
            ->defaultGroup('tenant_id')
            ->filters([
                SelectFilter::make('tenant_id')
                    ->label('Клиент')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordAction('edit')
            ->recordUrl(null)
            ->recordActions([
                Action::make('make_primary')
                    ->label('Сделать основным')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->action(function (TenantDomain $record) {
                        TenantDomain::where('tenant_id', $record->tenant_id)->update(['is_primary' => false]);
                        $record->update(['is_primary' => true]);
                    })
                    ->hidden(fn (TenantDomain $record): bool => (bool) $record->is_primary),
                Action::make('copy_host')
                    ->icon('heroicon-o-clipboard-document')
                    ->label('Копировать домен')
                    ->color('gray')
                    ->alpineClickHandler(function (TenantDomain $record): string {
                        $hostJson = Js::from($record->host);

                        return <<<JS
                            (async () => {
                                const text = {$hostJson};
                                const copyFallback = () => {
                                    const el = document.createElement('textarea');
                                    el.value = text;
                                    el.setAttribute('readonly', '');
                                    el.style.position = 'fixed';
                                    el.style.left = '-9999px';
                                    document.body.appendChild(el);
                                    el.select();
                                    try {
                                        document.execCommand('copy');
                                    } finally {
                                        document.body.removeChild(el);
                                    }
                                };
                                try {
                                    if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
                                        await navigator.clipboard.writeText(text);
                                    } else {
                                        copyFallback();
                                    }
                                } catch (e) {
                                    try {
                                        copyFallback();
                                    } catch (e2) {
                                        window.prompt('Скопируйте вручную (Ctrl+C):', text);
                                    }
                                }
                            })()
                        JS;
                    }),
                EditAction::make()->slideOver(),
                AdminFilamentDelete::configureTableDeleteAction(
                    DeleteAction::make()
                        ->label('Удалить')
                        ->modalHeading('Удалить домен?')
                        ->modalDescription('Сайт может перестать открываться по этому адресу. У клиента должен остаться хотя бы один домен.')
                        ->failureNotificationTitle('Нельзя удалить последний домен клиента')
                        ->failureNotificationBody('Добавьте другой домен или отключите клиента иначе — без адреса сайт недоступен.'),
                    ['entry' => 'filament.platform.tenant_domain.table'],
                ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Удалить домены?')
                        ->modalDescription('Сайт клиента может перестать открываться по этим адресам. Нельзя удалить все домены у одного клиента — у каждого должен остаться хотя бы один.')
                        ->using(function (DeleteBulkAction $action, EloquentCollection|Collection|LazyCollection $records): void {
                            $records = $records instanceof LazyCollection
                                ? $records->collect()
                                : Collection::wrap($records);

                            foreach ($records->groupBy('tenant_id') as $tenantId => $group) {
                                $selectedCount = $group->count();
                                $totalForTenant = TenantDomain::query()->where('tenant_id', $tenantId)->count();

                                if ($totalForTenant - $selectedCount < 1) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Нельзя удалить все домены клиента')
                                        ->body('Снимите часть выделения или оставьте хотя бы один домен у каждого клиента.')
                                        ->send();

                                    $action->halt();
                                }
                            }

                            $mayNotifyUser = true;

                            $records->each(static function (Model $record) use ($action, &$mayNotifyUser): void {
                                $ok = AdminDeleteExecutor::tryDeleteOneForBulk(
                                    $record,
                                    ['entry' => 'filament.platform.tenant_domain.bulk'],
                                    $mayNotifyUser,
                                );

                                if ($ok) {
                                    return;
                                }

                                $action->reportBulkProcessingFailure();
                            });
                        }),
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
        ];
    }
}
