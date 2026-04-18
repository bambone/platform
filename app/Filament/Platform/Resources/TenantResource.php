<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\TenantResource\Pages;
use App\Filament\Platform\Resources\TenantResource\RelationManagers\TenantMailLogsRelationManager;
use App\Filament\Platform\Resources\TenantResource\RelationManagers\TenantStorageQuotaEventsRelationManager;
use App\Filament\Platform\Resources\TenantResource\RelationManagers\TenantUsersRelationManager;
use App\Filament\Shared\Lifecycle\AdminFilamentDelete;
use App\Filament\Shared\TimezoneSelect;
use App\Filament\Shared\TenantAnalyticsFormSchema;
use App\Filament\Support\TenantPushPlatformFormSchema;
use App\Models\DomainLocalizationPreset;
use App\Models\Plan;
use App\Models\TemplatePreset;
use App\Models\Tenant;
use App\Models\TenantStorageQuota;
use App\Models\User;
use App\Providers\Filament\PlatformPanelProvider;
use App\Scheduling\SchedulingTimezoneOptions;
use App\Support\TenantRegionalContract;
use App\Support\TenantSlug;
use App\Support\Storage\MediaDeliveryMode;
use App\Support\Storage\MediaWriteMode;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use UnitEnum;

class TenantResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = Tenant::class;

    protected static ?string $modelLabel = 'Клиент';

    protected static ?string $pluralModelLabel = 'Клиенты';

    protected static ?string $navigationLabel = 'Клиенты';

    /** Совпадает с {@see PlatformPanelProvider} → группа «Клиенты». */
    protected static string|UnitEnum|null $navigationGroup = 'Клиенты';

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
                    ->description('Название, идентификатор и статус клиента в платформе.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Например: ООО «Прокат»'),
                        TextInput::make('legal_name')
                            ->label('Юридическое название')
                            ->maxLength(255)
                            ->helperText('Для договоров и счетов, если отличается от краткого названия.'),
                        TextInput::make('slug')
                            ->label('URL-идентификатор')
                            ->required()
                            ->maxLength(255)
                            ->rules([
                                function () {
                                    return function (string $attribute, mixed $value, \Closure $fail): void {
                                        $n = TenantSlug::normalize((string) $value);
                                        if (! TenantSlug::isValidProductSlug($n)) {
                                            $fail('Идентификатор: латиница, цифры и одиночные дефисы между фрагментами (без дефиса в начале или конце).');
                                        }
                                    };
                                },
                            ])
                            ->helperText('Технический поддомен и ссылки: латиница, цифры, дефис.'),
                        Select::make('status')
                            ->label('Статус клиента')
                            ->options(Tenant::statuses())
                            ->default('trial')
                            ->required()
                            ->helperText('Пробный / активен / приостановлен / архив.'),
                    ])->columns(2),

                Section::make('Бренд')
                    ->schema([
                        TextInput::make('brand_name')
                            ->label('Бренд на сайте')
                            ->maxLength(255)
                            ->helperText('Как называть клиента для посетителей; можно совпадать с названием.'),
                    ])->columns(2),

                Section::make('Внешний вид сайта')
                    ->description('Тема публичного сайта и терминология в кабинете — не путать с DNS-доменом (см. подзаголовок страницы).')
                    ->schema([
                        Select::make('theme_key')
                            ->label('Тема публичного сайта')
                            ->options([
                                'default' => 'По умолчанию',
                                'moto' => 'Мото',
                                'expert_auto' => 'Инструктор / автошкола (expert_auto)',
                                'advocate_editorial' => 'Адвокат / персональный бренд (advocate_editorial)',
                            ])
                            ->default('default')
                            ->required()
                            ->helperText('Ключ = каталог Blade tenant/themes/{ключ}. Не используйте пустой плейсхолдер «auto» в БД — для мотопроката: moto или default; для лендинга инструктора: expert_auto.'),
                        Select::make('domain_localization_preset_id')
                            ->label('Терминология интерфейса')
                            ->relationship(
                                name: 'domainLocalizationPreset',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('sort_order')
                            )
                            ->preload()
                            ->default(fn (): ?int => DomainLocalizationPreset::query()->where('slug', 'generic_services')->value('id'))
                            ->helperText('Подписи сущностей в кабинете клиента.'),
                    ])->columns(2),

                Section::make('Тариф')
                    ->schema([
                        Select::make('plan_id')
                            ->label('Тариф')
                            ->relationship('plan', 'name')
                            ->default(fn (): ?int => Plan::defaultIdForOnboarding())
                            ->preload()
                            ->helperText('Лимиты и функции. Для Push/PWA в тарифе должна быть отмечена функция «OneSignal Web Push…» (Платформа → Тарифы → редактирование). При создании клиента без выбора подставляется первый доступный активный тариф (как в мастере).'),
                        Select::make('template_preset_id')
                            ->label('Шаблон сайта при создании')
                            ->options(TemplatePreset::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visibleOn('create')
                            ->helperText('После сохранения копируется на сайт клиента.'),
                    ])->columns(2),

                Section::make('Контакты платформы')
                    ->description('Сотрудники платформы для учёта по клиенту. Состав кабинета клиента — вкладка «Команда».')
                    ->schema([
                        Select::make('owner_user_id')
                            ->label('Владелец (контакт)')
                            ->relationship('owner', 'name')
                            ->preload(),
                        Select::make('support_manager_id')
                            ->label('Менеджер поддержки')
                            ->relationship('supportManager', 'name')
                            ->preload(),
                    ])->columns(2),

                Section::make('Лимит почты')
                    ->description('Исходящая транзакционная почта кабинета клиента, писем в минуту на клиента.')
                    ->schema([
                        TextInput::make('mail_rate_limit_per_minute')
                            ->label('Писем в минуту (на клиента)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->default(10)
                            ->required()
                            ->helperText('Позже может выводиться из тарифа; иначе см. MAIL_TENANT_PER_MINUTE.'),
                    ]),

                Section::make('Медиа (инфраструктура)')
                    ->description('Переопределение режимов записи и отдачи публичных файлов. Пусто = глобальный дефолт платформы / env.')
                    ->schema([
                        Select::make('media_write_mode_override')
                            ->label('Режим записи файлов (переопределение)')
                            ->options([
                                MediaWriteMode::LocalOnly->value => 'Только локальное хранилище (local_only)',
                                MediaWriteMode::R2Only->value => 'Только облако R2 (r2_only)',
                                MediaWriteMode::Dual->value => 'Двойная запись: локально и в облаке (dual)',
                            ])
                            ->native(true)
                            ->placeholder('Нет')
                            ->helperText('Где физически сохраняются загрузки. Dual — копия на сервере и в облаке; узкие режимы — только для обслуживания и миграций.'),
                        Select::make('media_delivery_mode_override')
                            ->label('Режим отдачи файлов посетителям (переопределение)')
                            ->options([
                                MediaDeliveryMode::Local->value => 'С сервера сайта (local)',
                                MediaDeliveryMode::R2->value => 'Из облака / CDN (r2)',
                            ])
                            ->native(true)
                            ->placeholder('Нет')
                            ->helperText('Откуда браузер загружает публичные URL медиа. Обычно согласуют с режимом записи после миграции в облако.'),
                    ])
                    ->columns(2),

                Section::make('Регион и валюта')
                    ->description('Влияет на время, формат и отображение цен в кабинете и на сайте.')
                    ->schema([
                        TimezoneSelect::make('timezone')
                            ->default(SchedulingTimezoneOptions::DEFAULT_IDENTIFIER),
                        TextInput::make('locale')
                            ->label('Локаль')
                            ->required()
                            ->default('ru')
                            ->maxLength(10)
                            ->helperText('Обязательно: код локали (например ru или en-US). Пустое значение сохранить нельзя.'),
                        TextInput::make('country')
                            ->label('Страна (код)')
                            ->maxLength(2)
                            ->placeholder('RU')
                            ->helperText('Двухбуквенный код ISO, если нужен для настроек.'),
                        TextInput::make('currency')
                            ->label('Валюта')
                            ->required()
                            ->default('RUB')
                            ->maxLength(3)
                            ->placeholder('RUB')
                            ->helperText('Обязательно: трёхбуквенный код ISO 4217 (например RUB). Пустое значение сохранить нельзя.'),
                    ])->columns(2),

                TenantPushPlatformFormSchema::section(),

                TenantAnalyticsFormSchema::section(
                    function (): bool {
                        $u = Auth::user();
                        if (! $u instanceof User) {
                            return false;
                        }

                        return $u->hasAnyRole(['platform_owner', 'platform_admin']);
                    }
                ),

                Section::make('Хранилище и квоты')
                    ->description('Метрики по расписанию и кнопками в шапке страницы.')
                    ->visibleOn('edit')
                    ->schema([
                        Placeholder::make('sq_progress')
                            ->label('Заполнение')
                            ->content(fn (Tenant $record): HtmlString => self::storageUsageProgressHtml($record)),
                        Placeholder::make('sq_breakdown')
                            ->label('Разбивка по категориям')
                            ->content(fn (Tenant $record): HtmlString => self::storageBreakdownHtml($record)),
                        Placeholder::make('sq_used')
                            ->label('Использовано')
                            ->content(function (Tenant $record): string {
                                $record->loadMissing('storageQuota');
                                $q = $record->storageQuota;

                                return $q ? Number::fileSize((int) $q->used_bytes, precision: 2) : '—';
                            }),
                        Placeholder::make('sq_quota')
                            ->label('Эффективный лимит')
                            ->content(function (Tenant $record): string {
                                $record->loadMissing('storageQuota');
                                $q = $record->storageQuota;

                                return $q ? Number::fileSize($q->effective_quota_bytes, precision: 2) : '—';
                            }),
                        Placeholder::make('sq_free')
                            ->label('Свободно')
                            ->content(function (Tenant $record): string {
                                $record->loadMissing('storageQuota');
                                $q = $record->storageQuota;
                                if ($q === null) {
                                    return '—';
                                }
                                $free = max(0, $q->effective_quota_bytes - (int) $q->used_bytes);

                                return Number::fileSize($free, precision: 2);
                            }),
                        Placeholder::make('sq_status')
                            ->label('Статус')
                            ->content(function (Tenant $record): string {
                                $record->loadMissing('storageQuota');
                                $q = $record->storageQuota;
                                if ($q === null) {
                                    return '—';
                                }

                                return match ($q->status) {
                                    'warning_20' => 'Предупреждение (остаток ≤20%)',
                                    'critical_10' => 'Критично (остаток ≤10%)',
                                    'exceeded' => 'Переполнение',
                                    default => 'Норма',
                                };
                            }),
                        Placeholder::make('sq_package')
                            ->label('Пакет хранилища')
                            ->content(function (Tenant $record): string {
                                $record->loadMissing('storageQuota');
                                $q = $record->storageQuota;
                                $l = $q?->storage_package_label;

                                return (is_string($l) && $l !== '') ? $l : '—';
                            }),
                        Placeholder::make('sq_sync')
                            ->label('Последняя синхронизация')
                            ->content(function (Tenant $record): string {
                                $record->loadMissing('storageQuota');
                                $q = $record->storageQuota;

                                return $q?->last_synced_from_storage_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?? '—';
                            }),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Идентификатор')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (Tenant::statuses()[$state] ?? $state) : '—')
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'trial' => 'warning',
                        'suspended' => 'danger',
                        'archived' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('plan.name')
                    ->label('Тариф')
                    ->placeholder('—'),
                TextColumn::make('domainLocalizationPreset.name')
                    ->label('Терминология')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('cabinet_admin_url')
                    ->label('Кабинет клиента')
                    ->getStateUsing(fn (Tenant $record): ?string => $record->cabinetAdminUrl())
                    ->formatStateUsing(function (?string $state): string {
                        if (blank($state)) {
                            return '—';
                        }
                        $host = parse_url($state, PHP_URL_HOST);

                        return is_string($host) && $host !== ''
                            ? $host.'/admin'
                            : $state;
                    })
                    ->url(fn (Tenant $record): ?string => $record->cabinetAdminUrl())
                    ->openUrlInNewTab()
                    ->tooltip(fn (Tenant $record): ?string => $record->cabinetAdminUrl())
                    ->placeholder('Нет активного домена'),
                TextColumn::make('storageQuota.used_bytes')
                    ->label('Хранилище')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? Number::fileSize($state, precision: 1) : '—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('storageQuota.effective')
                    ->label('Квота')
                    ->getStateUsing(fn (Tenant $record): ?int => $record->storageQuota?->effective_quota_bytes)
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? Number::fileSize($state, precision: 1) : '—')
                    ->toggleable(),
                TextColumn::make('storageQuota.used_pct')
                    ->label('%')
                    ->getStateUsing(function (Tenant $record): ?float {
                        $q = $record->storageQuota;
                        if ($q === null) {
                            return null;
                        }
                        $eff = $q->effective_quota_bytes;
                        if ($eff <= 0) {
                            return null;
                        }

                        return round(((int) $q->used_bytes / $eff) * 100, 1);
                    })
                    ->toggleable(),
                TextColumn::make('storageQuota.status')
                    ->label('Ст. хран.')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'warning_20' => 'warning',
                        'critical_10' => 'danger',
                        'exceeded' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'warning_20' => '20%',
                        'critical_10' => '10%',
                        'exceeded' => 'Переполн.',
                        default => 'OK',
                    })
                    ->toggleable(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['domains', 'storageQuota']))
            ->filters([
                SelectFilter::make('storage_status')
                    ->label('Хранилище')
                    ->options([
                        'approaching' => 'У границы (≤20% свободно)',
                        'critical' => 'Критично (≤10% свободно)',
                        'exceeded' => 'Переполнение',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $v = $data['value'] ?? null;
                        if ($v === null || $v === '') {
                            return $query;
                        }

                        return match ($v) {
                            'approaching' => $query->whereHas('storageQuota', fn (Builder $q): Builder => $q->whereIn('status', ['warning_20', 'critical_10', 'exceeded'])),
                            'critical' => $query->whereHas('storageQuota', fn (Builder $q): Builder => $q->whereIn('status', ['critical_10', 'exceeded'])),
                            'exceeded' => $query->whereHas('storageQuota', fn (Builder $q): Builder => $q->where('status', 'exceeded')),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('setMediaWriteOverride')
                        ->label('Задать режим записи медиа')
                        ->form([
                            Select::make('mode')
                                ->label('Режим записи')
                                ->options([
                                    MediaWriteMode::LocalOnly->value => 'Только локальное хранилище (local_only)',
                                    MediaWriteMode::R2Only->value => 'Только облако R2 (r2_only)',
                                    MediaWriteMode::Dual->value => 'Двойная запись (dual)',
                                ])
                                ->required()
                                ->native(true),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (Tenant $t) => $t->update(['media_write_mode_override' => $data['mode']]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('setMediaDeliveryOverride')
                        ->label('Задать режим отдачи медиа')
                        ->form([
                            Select::make('mode')
                                ->label('Режим отдачи')
                                ->options([
                                    MediaDeliveryMode::Local->value => 'С сервера сайта (local)',
                                    MediaDeliveryMode::R2->value => 'Из облака / CDN (r2)',
                                ])
                                ->required()
                                ->native(true),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (Tenant $t) => $t->update(['media_delivery_mode_override' => $data['mode']]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('clearMediaOverrides')
                        ->label('Сбросить переопределения медиа')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each(fn (Tenant $t) => $t->update([
                                'media_write_mode_override' => null,
                                'media_delivery_mode_override' => null,
                            ]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    AdminFilamentDelete::makeBulkDeleteAction()
                        ->modalHeading('Удалить выбранных клиентов?')
                        ->modalDescription('Действие необратимо: клиенты и связанные данные могут быть удалены из базы. Сайты перестанут открываться. Продолжайте только если это осознанное решение.'),
                ]),
            ])
            ->emptyStateHeading('Клиентов пока нет')
            ->emptyStateDescription('Создайте первого клиента мастером «Новый клиент» или кнопкой «Создать».')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }

    /**
     * Порядок = порядок вкладок на EditTenant (combined tabs). Первая вкладка — форма «Клиент», затем RM в этом порядке.
     * Tab order: Client (form) → Team → Mail → Storage.
     */
    public static function getRelations(): array
    {
        return [
            TenantUsersRelationManager::class,
            TenantMailLogsRelationManager::class,
            TenantStorageQuotaEventsRelationManager::class,
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

    /**
     * Визуализация использования квоты по {@see TenantStorageQuota}; без деления на ноль и без JS.
     */
    private static function storageUsageProgressHtml(Tenant $record): HtmlString
    {
        $record->loadMissing('storageQuota');
        $q = $record->storageQuota;
        if ($q === null) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Нет записи квоты.</p>');
        }

        $used = max(0, (int) $q->used_bytes);
        $effective = max(0, (int) $q->effective_quota_bytes);
        $status = (string) ($q->status ?? 'ok');

        if ($effective <= 0) {
            return new HtmlString(
                '<p class="text-sm text-gray-600 dark:text-gray-300">Лимит 0 — использовано '.e(Number::fileSize($used, precision: 2)).'.</p>'
            );
        }

        $ratio = $used / $effective;
        $pctDisplay = min(999.9, max(0.0, round($ratio * 100, 1)));
        $barWidth = min(100.0, max(0.0, round($ratio * 100, 2)));
        $overflow = $used > $effective;

        $barColor = match ($status) {
            'exceeded', 'critical_10' => '#dc2626',
            'warning_20' => '#ca8a04',
            default => $overflow ? '#dc2626' : '#2563eb',
        };

        $caption = e((string) $pctDisplay).'% · '.e(Number::fileSize($used, precision: 2)).' / '.e(Number::fileSize($effective, precision: 2));
        if ($overflow) {
            $caption .= ' · переполнение';
        }

        return new HtmlString(
            '<div class="space-y-2">'
            .'<div class="h-2 w-full overflow-hidden rounded-md bg-gray-200 dark:bg-gray-700">'
            .'<div class="h-2 rounded-md" style="width: '.$barWidth.'%; background-color: '.e($barColor).';"></div>'
            .'</div>'
            .'<p class="text-xs text-gray-600 dark:text-gray-400">'.$caption.'</p>'
            .'</div>'
        );
    }

    /**
     * Компактная таблица по полям {@see TenantStorageScanResult::toSummaryJson()} (последний пересчёт).
     */
    private static function storageBreakdownHtml(Tenant $record): HtmlString
    {
        $record->loadMissing('storageQuota');
        $q = $record->storageQuota;
        $raw = $q?->last_scan_summary_json;
        if ($raw === null || $raw === [] || ! is_array($raw)) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Запустите пересчёт хранилища для разбивки.</p>');
        }

        $rows = [
            ['Брендинг', 'branding_bytes'],
            ['Медиа', 'media_bytes'],
            ['SEO', 'seo_bytes'],
            ['Прочее', 'other_bytes'],
        ];

        $tbody = '';
        foreach ($rows as [$label, $key]) {
            $cell = self::storageSummaryBytesCell($raw, $key);
            $tbody .= '<tr>'
                .'<th scope="row" class="px-2 py-1 text-start text-xs font-medium text-gray-700 dark:text-gray-300">'.e($label).'</th>'
                .'<td class="px-2 py-1 text-end text-xs text-gray-900 tabular-nums dark:text-gray-100">'.$cell.'</td>'
                .'</tr>';
        }

        return new HtmlString(
            '<div class="overflow-x-auto rounded-md border border-gray-200 dark:border-white/10">'
            .'<table class="w-full min-w-[12rem] border-collapse">'
            .'<tbody>'.$tbody.'</tbody>'
            .'</table>'
            .'</div>'
        );
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private static function storageSummaryBytesCell(array $summary, string $key): string
    {
        if (! array_key_exists($key, $summary)) {
            return e('—');
        }
        $v = $summary[$key];
        if (! is_numeric($v)) {
            return e('—');
        }

        $bytes = (int) $v;
        if ($bytes === 0) {
            return e('0 B');
        }

        return e(Number::fileSize($bytes, precision: 2));
    }
}
