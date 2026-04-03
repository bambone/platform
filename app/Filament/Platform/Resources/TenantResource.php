<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\TenantResource\Pages;
use App\Filament\Platform\Resources\TenantResource\RelationManagers\TenantMailLogsRelationManager;
use App\Filament\Platform\Resources\TenantResource\RelationManagers\TenantStorageQuotaEventsRelationManager;
use App\Filament\Platform\Resources\TenantResource\RelationManagers\TenantUsersRelationManager;
use App\Filament\Shared\TenantAnalyticsFormSchema;
use App\Models\DomainLocalizationPreset;
use App\Models\TemplatePreset;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
use Illuminate\Support\Number;

class TenantResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = Tenant::class;

    protected static ?string $modelLabel = 'Клиент';

    protected static ?string $pluralModelLabel = 'Клиенты';

    protected static ?string $navigationLabel = 'Клиенты';

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
                    ->description('Клиент платформы — отдельный сайт и данные. Название и URL-идентификатор часто видны в адресах и внутренних списках.')
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
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Используется в техническом поддомене и ссылках. Латиница, цифры и дефис.'),
                        Select::make('theme_key')
                            ->label('Тема публичного сайта')
                            ->options([
                                'default' => 'По умолчанию',
                                'moto' => 'Мото',
                                'auto' => 'Авто',
                            ])
                            ->default('default')
                            ->required()
                            ->helperText('Пресет внешнего вида (Blade в tenant/themes/{ключ}). Недостающие страницы берутся из слоя default и движка.'),
                        Select::make('domain_localization_preset_id')
                            ->label('Тематика терминологии')
                            ->relationship(
                                name: 'domainLocalizationPreset',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('sort_order')
                            )
                            ->preload()
                            ->default(fn (): ?int => DomainLocalizationPreset::query()->where('slug', 'generic_services')->value('id'))
                            ->helperText('Подписи сущностей в кабинете клиента (заявки, брони, каталог). Не смешивать с темой оформления сайта выше.'),
                        TextInput::make('brand_name')
                            ->label('Бренд на сайте')
                            ->maxLength(255)
                            ->helperText('Как называть клиента для посетителей; можно совпадать с названием.'),
                        Select::make('status')
                            ->label('Статус клиента')
                            ->options(Tenant::statuses())
                            ->default('trial')
                            ->required()
                            ->helperText('Пробный — ограниченный период. Активен — полноценная работа. Приостановлен — доступ ограничен.'),
                        Select::make('plan_id')
                            ->label('Тариф')
                            ->relationship('plan', 'name')
                            ->preload()
                            ->helperText('Определяет лимиты и доступные функции.'),
                        Select::make('template_preset_id')
                            ->label('Шаблон сайта при создании')
                            ->options(TemplatePreset::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visibleOn('create')
                            ->helperText('После сохранения шаблон копируется в сайт клиента. При редактировании клиента поле скрыто — шаблон уже применён.'),
                    ])->columns(2),

                Section::make('Ответственные')
                    ->description('Не обязательно сразу. Владелец и менеджер поддержки помогают ориентироваться в карточке клиента.')
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

                Section::make('Лимиты и доставка почты')
                    ->description('Исходящие письма кабинета клиента (транзакционная почта) ограничиваются в минуту на клиента, чтобы не перегружать SMTP и очередь.')
                    ->schema([
                        TextInput::make('mail_rate_limit_per_minute')
                            ->label('Писем в минуту (на клиента)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->default(10)
                            ->required()
                            ->helperText('Значение из карточки клиента; позже может выводиться из тарифа. По умолчанию из MAIL_TENANT_PER_MINUTE, если поле некорректно.'),
                    ]),

                Section::make('Регион и валюта')
                    ->description('Влияет на время, формат и отображение цен в кабинете и на сайте.')
                    ->schema([
                        TextInput::make('timezone')
                            ->label('Часовой пояс')
                            ->default('Europe/Moscow')
                            ->maxLength(50)
                            ->helperText('Например: Europe/Moscow — для календаря и бронирований.'),
                        TextInput::make('locale')
                            ->label('Локаль')
                            ->default('ru')
                            ->maxLength(10)
                            ->helperText('Язык интерфейса кабинета и сайта, если поддерживается темой.'),
                        TextInput::make('country')
                            ->label('Страна (код)')
                            ->maxLength(2)
                            ->placeholder('RU')
                            ->helperText('Двухбуквенный код ISO, если нужен для настроек.'),
                        TextInput::make('currency')
                            ->label('Валюта')
                            ->default('RUB')
                            ->maxLength(3)
                            ->placeholder('RUB')
                            ->helperText('Трёхбуквенный код для цен и отчётов.'),
                    ])->columns(2),

                TenantAnalyticsFormSchema::section(
                    fn (): bool => auth()->user()?->hasAnyRole(['platform_owner', 'platform_admin']) ?? false
                ),

                Section::make('Хранилище и квоты')
                    ->description('Метрики обновляются при сохранении и по ночному расписанию. Управление — кнопками над формой.')
                    ->visibleOn('edit')
                    ->schema([
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
                    DeleteBulkAction::make()
                        ->modalHeading('Удалить выбранных клиентов?')
                        ->modalDescription('Действие необратимо: клиенты и связанные данные могут быть удалены из базы. Сайты перестанут открываться. Продолжайте только если это осознанное решение.'),
                ]),
            ])
            ->emptyStateHeading('Клиентов пока нет')
            ->emptyStateDescription('Создайте первого клиента мастером «Новый клиент» или кнопкой «Создать».')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }

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
}
