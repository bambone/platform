<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources;

use App\Filament\Shared\Lifecycle\AdminFilamentDelete;
use App\Filament\Support\AdminEmptyState;
use App\Filament\Tenant\Resources\BookableServiceResource\Pages;
use App\Models\BookableService;
use App\Models\BookingSettingsPreset;
use App\Scheduling\BookableServiceBulkService;
use App\Scheduling\Enums\BookableServiceSettingsApplyMode;
use App\Scheduling\Enums\SchedulingScope;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class BookableServiceResource extends Resource
{
    private const ONLINE_BOOKING_BULK_HINT = 'Действие активирует связанную услугу и цель расписания. Чтобы клиенты видели слоты, у целей должны быть настроены ресурсы расписания и правила доступности.';

    protected static ?string $model = BookableService::class;

    protected static ?string $navigationLabel = 'Услуги (запись)';

    protected static ?string $modelLabel = 'Услуга';

    protected static ?string $pluralModelLabel = 'Услуги';

    protected static string|UnitEnum|null $navigationGroup = 'SchedulingCore';

    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    public static function canAccess(): bool
    {
        $tenant = currentTenant();

        return $tenant !== null
            && $tenant->scheduling_module_enabled
            && Gate::allows('manage_scheduling');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('scheduling_scope', SchedulingScope::Tenant)
            ->where('tenant_id', currentTenant()?->id)
            ->with(['motorcycle', 'rentalUnit.motorcycle']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Связь с каталогом')
                    ->description('Привязка к мотоциклу или единице парка задаётся только из соответствующей карточки. Здесь — просмотр.')
                    ->schema([
                        Placeholder::make('linked_notice')
                            ->label('')
                            ->content(function (?BookableService $record): HtmlString {
                                if ($record === null || $record->isStandalone()) {
                                    return new HtmlString('');
                                }
                                $msg = $record->isMotorcycleLinked()
                                    ? 'Эта запись управляется из карточки мотоцикла в каталоге (раздел «Онлайн-запись»).'
                                    : 'Эта запись управляется из карточки единицы парка (раздел «Онлайн-запись»).';

                                return new HtmlString(
                                    '<p class="text-sm text-amber-800 dark:text-amber-200/90">'.e($msg).'</p>'
                                );
                            })
                            ->visible(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone()),
                        Placeholder::make('binding_preview')
                            ->label('Привязка')
                            ->content(fn (?BookableService $record): string => $record === null ? '—' : $record->bindingLabel())
                            ->visible(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone()),
                    ])
                    ->visible(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone())
                    ->columns(1),
                Section::make('Основное')
                    ->description('Как услуга называется и что видит клиент на странице записи.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->disabled(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone())
                            ->afterStateUpdated(function (string $operation, $state, callable $set) {
                                if ($operation !== 'create') {
                                    return;
                                }
                                $set('slug', Str::slug((string) $state));
                            }),
                        TextInput::make('slug')
                            ->label('Адрес в URL (slug)')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone())
                            ->helperText('Короткий идентификатор в ссылке. Уникален в пределах вашего клиента, латиница и дефисы.'),
                        Textarea::make('description')
                            ->label('Описание')
                            ->rows(3)
                            ->disabled(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone())
                            ->helperText('Необязательно: кратко, что входит в услугу.'),
                    ]),
                Section::make('Длительность и сетка времени')
                    ->description('Сколько длится приём и как часто система предлагает варианты начала.')
                    ->schema([
                        TextInput::make('duration_minutes')
                            ->label('Длительность приёма')
                            ->suffix('мин')
                            ->numeric()
                            ->minValue(1)
                            ->default(60)
                            ->required()
                            ->disabled(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone())
                            ->helperText('Чистое время услуги для клиента, без перерывов до и после.'),
                        TextInput::make('slot_step_minutes')
                            ->label('Шаг между слотами')
                            ->suffix('мин')
                            ->numeric()
                            ->minValue(5)
                            ->default(15)
                            ->required()
                            ->disabled(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone())
                            ->helperText('Интервал между возможными временами начала. Например, 15 — 10:00, 10:15, 10:30…. Не меньше 5 минут.'),
                    ]),
                Section::make('Перерывы вокруг приёма')
                    ->description('Дополнительные минуты до и после, чтобы в календаре не стояли встречи вплотную.')
                    ->schema([
                        TextInput::make('buffer_before_minutes')
                            ->label('Запас до начала')
                            ->suffix('мин')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->disabled(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone())
                            ->helperText('Время на подготовку перед тем, как клиент приходит или начинается услуга.'),
                        TextInput::make('buffer_after_minutes')
                            ->label('Запас после окончания')
                            ->suffix('мин')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->disabled(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone())
                            ->helperText('Время после приёма, пока слот считается занятым (уборка, переход к следующему клиенту).'),
                    ]),
                Section::make('Правила онлайн-записи')
                    ->description('Ограничения по времени: как рано и как поздно клиент может выбрать слот.')
                    ->schema([
                        TextInput::make('min_booking_notice_minutes')
                            ->label('Минимум времени до начала слота')
                            ->suffix('мин')
                            ->numeric()
                            ->minValue(0)
                            ->default(120)
                            ->required()
                            ->disabled(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone())
                            ->helperText('Сколько минут должно пройти от момента записи до начала выбранного слота. Пример: 120 — нельзя записаться меньше чем за 2 часа; 0 — можно взять ближайшее свободное время.'),
                        TextInput::make('max_booking_horizon_days')
                            ->label('Запись не дальше, чем')
                            ->suffix('дн.')
                            ->numeric()
                            ->minValue(1)
                            ->default(60)
                            ->required()
                            ->disabled(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone())
                            ->helperText('Клиент не увидит слотов позже этой границы от сегодняшней даты.'),
                    ]),
                Section::make('Статус и порядок в списке')
                    ->description('Подтверждение вручную — заявка создаётся, но визит нужно утвердить в кабинете. Неактивная услуга не показывается при записи.')
                    ->schema([
                        Toggle::make('requires_confirmation')
                            ->label('Подтверждать заявку вручную')
                            ->default(true)
                            ->disabled(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone()),
                        Toggle::make('is_active')
                            ->label('Показывать при онлайн-записи')
                            ->default(true)
                            ->disabled(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone())
                            ->helperText('Для услуг из карточки мотоцикла или единицы парка включение и выключение — в той карточке.'),
                        TextInput::make('sort_weight')
                            ->label('Порядок в списке')
                            ->numeric()
                            ->default(0)
                            ->disabled(fn (?BookableService $record): bool => $record !== null && ! $record->isStandalone())
                            ->helperText('Меньшее число — выше на публичной странице, при одинаковом значении порядок может быть любым.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminEmptyState::applyInitial(
            $table
                ->columns([
                    TextColumn::make('title')->label('Название')->searchable(),
                    TextColumn::make('link_type_display')
                        ->label('Связь')
                        ->badge()
                        ->getStateUsing(function (BookableService $record): string {
                            return match ($record->linkType()->value) {
                                'standalone' => 'Отдельная',
                                'motorcycle' => 'Модель',
                                'rental_unit' => 'Ед. парка',
                                default => $record->linkType()->value,
                            };
                        })
                        ->color(function (BookableService $record): string {
                            return match ($record->linkType()->value) {
                                'standalone' => 'gray',
                                'motorcycle' => 'info',
                                'rental_unit' => 'warning',
                                default => 'gray',
                            };
                        }),
                    TextColumn::make('binding_label_display')
                        ->label('Привязка')
                        ->getStateUsing(fn (BookableService $record): string => $record->bindingLabel()),
                    TextColumn::make('slug')->label('URL-идентификатор'),
                    IconColumn::make('is_active')->label('Активна')->boolean(),
                    TextColumn::make('duration_minutes')->label('Длит., мин'),
                ])
                ->defaultSort('sort_weight')
                ->actions([EditAction::make()])
                ->bulkActions([
                    BulkActionGroup::make([
                        BulkAction::make('apply_booking_preset')
                            ->label('Применить группу настроек')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->form([
                                Select::make('preset_id')
                                    ->label('Группа настроек')
                                    ->options(fn (): array => BookingSettingsPreset::query()
                                        ->where('tenant_id', currentTenant()?->id)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Toggle::make('also_enable_online_booking')
                                    ->label('Также включить онлайн-запись')
                                    ->default(false),
                            ])
                            ->action(function (BulkAction $action, array $data): void {
                                $preset = BookingSettingsPreset::query()->find((int) ($data['preset_id'] ?? 0));
                                if ($preset === null) {
                                    Notification::make()->title('Группа не найдена')->danger()->send();

                                    return;
                                }
                                $also = (bool) ($data['also_enable_online_booking'] ?? false);
                                $bulk = app(BookableServiceBulkService::class);
                                foreach ($action->getSelectedRecords() as $record) {
                                    if (! $record instanceof BookableService) {
                                        continue;
                                    }
                                    try {
                                        $bulk->applyPresetToService(
                                            $record,
                                            $preset,
                                            $also,
                                            BookableServiceSettingsApplyMode::Replace,
                                        );
                                    } catch (\InvalidArgumentException) {
                                        Notification::make()->title('Ошибка: проверьте, что все выбранные услуги принадлежат вашему клиенту.')->danger()->send();

                                        return;
                                    }
                                }
                                Notification::make()->title('Настройки применены')->success()->send();
                            }),
                        BulkAction::make('enable_online_booking')
                            ->label('Включить онлайн-запись')
                            ->icon('heroicon-o-check-circle')
                            ->color('success')
                            ->requiresConfirmation()
                            ->modalHeading('Включить онлайн-запись для выбранных услуг?')
                            ->modalDescription(self::ONLINE_BOOKING_BULK_HINT)
                            ->action(function (BulkAction $action): void {
                                $bulk = app(BookableServiceBulkService::class);
                                try {
                                    $bulk->enableOnlineBookingForServices($action->getSelectedRecords());
                                } catch (\InvalidArgumentException) {
                                    Notification::make()->title('Ошибка доступа к данным клиента.')->danger()->send();

                                    return;
                                }
                                Notification::make()->title('Онлайн-запись включена')->success()->send();
                            }),
                        BulkAction::make('disable_online_booking')
                            ->label('Выключить онлайн-запись')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('Выключить онлайн-запись для выбранных услуг?')
                            ->modalDescription('Клиенты не смогут записаться на эти услуги, пока запись снова не включат.')
                            ->action(function (BulkAction $action): void {
                                $bulk = app(BookableServiceBulkService::class);
                                try {
                                    $bulk->disableOnlineBookingForServices($action->getSelectedRecords());
                                } catch (\InvalidArgumentException) {
                                    Notification::make()->title('Ошибка доступа к данным клиента.')->danger()->send();

                                    return;
                                }
                                Notification::make()->title('Онлайн-запись выключена')->success()->send();
                            }),
                        AdminFilamentDelete::makeBulkDeleteAction(),
                    ]),
                ]),
            'Услуг для записи пока нет',
            'Создайте услугу или привяжите её к модели в каталоге и единицам парка — тогда настроятся длительность и онлайн-запись.'
                .AdminEmptyState::hintFiltersAndSearch(),
            'heroicon-o-calendar-days',
            [CreateAction::make()->label('Создать услугу')],
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookableServices::route('/'),
            'create' => Pages\CreateBookableService::route('/create'),
            'edit' => Pages\EditBookableService::route('/{record}/edit'),
        ];
    }
}
