<?php

namespace App\Filament\Tenant\Resources;

use App\ContactChannels\ContactChannelRegistry;
use App\ContactChannels\ContactChannelType;
use App\ContactChannels\LeadContactActionResolver;
use App\Filament\Tenant\Concerns\ResolvesDomainTermLabels;
use App\Filament\Tenant\Forms\ManualOperatorBookingForm;
use App\Filament\Tenant\Resources\LeadResource\Pages;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\LeadActivityLog;
use App\Product\CRM\DTO\ManualBookingCreateData;
use App\Product\CRM\ManualLeadBookingService;
use App\Support\FilamentMotorcycleThumbnail;
use App\Support\PhoneNormalizer;
use App\Support\RussianPhone;
use App\Tenant\Filament\TenantCabinetUserPicker;
use App\Terminology\DomainTermKeys;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Filament\Tenant\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class LeadResource extends Resource
{
    use ResolvesDomainTermLabels;

    /**
     * Одна колонка на всех брейкпоинтах: иначе {@see Component::columns(1)} задаёт только `lg`,
     * а дочерняя {@see Schema} наследует счётчик колонок и может класть блоки в одну строку.
     *
     * @var array<string, int>
     */
    private const FORM_SECTION_SINGLE_COLUMN = [
        'default' => 1,
        'sm' => 1,
        'md' => 1,
        'lg' => 1,
        'xl' => 1,
        '2xl' => 1,
    ];

    protected static ?string $model = Lead::class;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 20;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::LEAD_PLURAL, 'Обращения');
    }

    public static function getModelLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::LEAD, 'Обращение');
    }

    public static function getPluralModelLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::LEAD_PLURAL, 'Обращения');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['motorcycle.media']);
    }

    /**
     * Slide-over без кнопок «Сохранить» / «Отмена»: поля формы сохраняют запись сами (см. persist-хуки в form()).
     */
    protected static function leadSlideOverEditAction(): EditAction
    {
        return EditAction::make('edit_lead_slide')
            ->slideOver()
            ->extraModalWindowAttributes([
                'class' => 'fi-lead-edit-slide-over',
            ], merge: true)
            ->iconButton()
            ->label('Редактировать заявку')
            ->tooltip('Редактировать заявку')
            ->color('gray')
            ->modalDescription('Изменения сохраняются автоматически при смене значения. Закройте панель крестиком или кликом вне её.')
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }

    protected static function persistLeadFieldClosure(string $attribute): Closure
    {
        return function (mixed $state, mixed $old, Field $component) use ($attribute): void {
            static::persistLeadAttribute($component, $attribute, $state, $old);
        };
    }

    protected static function leadAttributeValuesAreEqual(string $attribute, mixed $current, mixed $newValue): bool
    {
        if (in_array($attribute, ['rental_date_from', 'rental_date_to'], true)) {
            $normalize = function (mixed $v): ?string {
                if ($v instanceof \DateTimeInterface) {
                    return $v->format('Y-m-d');
                }

                return $v === null || $v === '' ? null : (string) $v;
            };

            return $normalize($current) === $normalize($newValue);
        }

        if (in_array($attribute, ['motorcycle_id', 'assigned_user_id'], true)) {
            $n = $newValue === '' || $newValue === null ? null : (int) $newValue;
            $c = $current === '' || $current === null ? null : (int) $current;

            return $n === $c;
        }

        return (string) ($current ?? '') === (string) ($newValue ?? '');
    }

    protected static function persistLeadAttribute(
        Field $component,
        string $attribute,
        mixed $state,
        mixed $old,
    ): void {
        $record = $component->getRecord();
        if (! $record instanceof Lead || ! $record->exists) {
            return;
        }

        $newValue = $state === '' ? null : $state;

        if (static::leadAttributeValuesAreEqual($attribute, $record->getAttribute($attribute), $newValue)) {
            return;
        }

        if ($attribute === 'assigned_user_id' && $newValue !== null && $newValue !== '') {
            $tenant = currentTenant();
            if ($tenant === null) {
                Notification::make()->title('Не удалось сохранить')->body('Контекст клиента не найден.')->danger()->send();
                $component->state($old);

                return;
            }
            try {
                TenantCabinetUserPicker::assertUserBelongsToCabinetTeam(
                    $tenant->id,
                    (int) $newValue,
                    'assigned_user_id',
                );
            } catch (ValidationException $e) {
                $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();
                Notification::make()->title('Не удалось сохранить')->body($message)->danger()->send();
                $component->state($old);

                return;
            }
        }

        if ($attribute === 'name' && ($newValue === null || $newValue === '')) {
            Notification::make()->title('Имя обязательно')->warning()->send();
            $component->state($old);

            return;
        }

        if ($attribute === 'phone' && ($newValue === null || $newValue === '')) {
            Notification::make()->title('Телефон обязателен')->warning()->send();
            $component->state($old);

            return;
        }

        if ($attribute === 'email' && filled($newValue) && ! filter_var((string) $newValue, FILTER_VALIDATE_EMAIL)) {
            Notification::make()->title('Некорректный email')->warning()->send();
            $component->state($old);

            return;
        }

        try {
            $record->update([$attribute => $newValue]);
            $record->refresh();
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            Notification::make()->title('Не удалось сохранить')->body($message)->danger()->send();
            $component->state($old);
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Не удалось сохранить')->body('Повторите попытку.')->danger()->send();
            $component->state($old);
        }
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('CRM (входящая заявка)')
                    ->description('Полный операторский timeline, активность и учёт уведомлений — в разделе «Заявки» (колонка CRM в списке ведёт в карточку). Здесь Lead — downstream по аренде. LeadStatusHistory в БД — проекция для совместимости; источник истины по inbound — CRM-активность (ADR-007).')
                    ->schema([
                        TextInput::make('crm_request_id')
                            ->label('ID CRM-заявки')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('—'),
                    ])
                    ->columns(1)
                    ->collapsible(),
                Section::make('Контактные данные')
                    ->description('Как с вами связался потенциальный клиент. Структурированные каналы с сайта в MVP только для просмотра.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(static::persistLeadFieldClosure('name')),
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->required()
                            ->tel()
                            ->telRegex(RussianPhone::filamentTelDisplayRegex())
                            ->maxLength(20)
                            ->live(onBlur: true)
                            ->afterStateUpdated(static::persistLeadFieldClosure('phone')),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(static::persistLeadFieldClosure('email')),
                        TextInput::make('preferred_contact_channel')
                            ->label('Предпочтительный канал')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?string $state): string => $state ? ContactChannelRegistry::label($state) : '—'),
                        TextInput::make('preferred_contact_value')
                            ->label('Значение предпочтительного канала')
                            ->disabled()
                            ->dehydrated(false),
                        Placeholder::make('visitor_contact_channels_readonly')
                            ->label('Каналы посетителя (JSON)')
                            ->columnSpanFull()
                            ->extraAttributes([
                                'class' => 'fi-lead-visitor-json-field',
                            ], merge: true)
                            ->content(function (Lead $record): HtmlString {
                                $j = $record->visitor_contact_channels_json;
                                if (! is_array($j) || $j === []) {
                                    return new HtmlString('—');
                                }

                                $json = e(json_encode($j, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                                return new HtmlString(
                                    '<div class="fi-lead-visitor-channels-json rounded-lg border border-zinc-500/25 bg-zinc-950/40 p-3 dark:border-white/10 dark:bg-black/35">'
                                    .'<pre class="fi-body m-0 max-h-none min-h-0 whitespace-pre-wrap break-words text-xs leading-relaxed">'
                                    .$json
                                    .'</pre></div>'
                                );
                            }),
                    ])
                    ->columns(2),

                Section::make('Детали заявки')
                    ->description('Интерес к технике и даты; помогает менеджеру подготовить ответ.')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->extraAttributes(['class' => 'fi-lead-form-row fi-lead-details-grid'])
                            ->schema([
                                Select::make('motorcycle_id')
                                    ->label(new HtmlString('Интерес к каталогу<br><span class="text-gray-500 dark:text-gray-400">карточка мотоцикла</span>'))
                                    ->relationship('motorcycle', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText(new HtmlString('Модель из каталога, если клиент указал её в форме.<br>Оставьте пустым для общего запроса без техники.'))
                                    ->live()
                                    ->afterStateUpdated(static::persistLeadFieldClosure('motorcycle_id'))
                                    ->extraFieldWrapperAttributes(['class' => 'fi-lead-slideover-paired-field']),
                                DatePicker::make('rental_date_from')
                                    ->label(new HtmlString('Дата начала аренды<br><span class="text-gray-500 dark:text-gray-400">первый день</span>'))
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(static::persistLeadFieldClosure('rental_date_from'))
                                    ->helperText(new HtmlString('Как в заявке с сайта или по словам клиента.<br>Проверьте при переносе в бронирование.'))
                                    ->extraFieldWrapperAttributes(['class' => 'fi-lead-slideover-paired-field']),
                                DatePicker::make('rental_date_to')
                                    ->label(new HtmlString('Дата окончания аренды<br><span class="text-gray-500 dark:text-gray-400">последний день</span>'))
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(static::persistLeadFieldClosure('rental_date_to'))
                                    ->helperText(new HtmlString('План возврата по заявке; может сдвинуться при согласовании.<br>Уточняйте перед подтверждением брони.'))
                                    ->extraFieldWrapperAttributes(['class' => 'fi-lead-slideover-paired-field']),
                                Select::make('source')
                                    ->label(new HtmlString('Источник заявки<br><span class="text-gray-500 dark:text-gray-400">канал обращения</span>'))
                                    ->options(Lead::sources())
                                    ->helperText(new HtmlString('Сайт, звонок, офис, мессенджер и т.д.<br>Используется в отчётах и сценариях обработки.'))
                                    ->live()
                                    ->afterStateUpdated(static::persistLeadFieldClosure('source'))
                                    ->extraFieldWrapperAttributes(['class' => 'fi-lead-slideover-paired-field']),
                            ]),
                        Textarea::make('comment')
                            ->label('Комментарий клиента')
                            ->rows(3)
                            ->columnSpanFull()
                            ->live(onBlur: true)
                            ->afterStateUpdated(static::persistLeadFieldClosure('comment')),
                    ])
                    ->columns(self::FORM_SECTION_SINGLE_COLUMN),

                Section::make('Работа менеджера')
                    ->description('Видно только в кабинете; на сайт не выводится.')
                    ->schema([
                        Grid::make(2)
                            ->extraAttributes(['class' => 'fi-lead-form-row'])
                            ->schema([
                                Select::make('status')
                                    ->label('Статус заявки')
                                    ->options(Lead::statuses())
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(static::persistLeadFieldClosure('status'))
                                    ->helperText('Новая — ещё не обработана. В работе — менеджер связался. Завершена/отменена фиксируют исход.')
                                    ->extraFieldWrapperAttributes(['class' => 'fi-lead-slideover-paired-field']),
                                Select::make('assigned_user_id')
                                    ->label('Ответственный')
                                    ->relationship(
                                        'assignedUser',
                                        'name',
                                        modifyQueryUsing: function (Builder $query, ?string $search, Select $component): void {
                                            $tenantId = currentTenant()?->id;
                                            $record = $component->getRecord();
                                            if ($record instanceof Lead && $record->exists) {
                                                TenantCabinetUserPicker::applyCabinetTeamScopeWithLegacyAssignee(
                                                    $query,
                                                    $tenantId,
                                                    $record->assigned_user_id !== null ? (int) $record->assigned_user_id : null,
                                                );
                                            } else {
                                                TenantCabinetUserPicker::applyCabinetTeamScope($query, $tenantId);
                                            }
                                        },
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Кто ведёт заявку внутри команды.')
                                    ->live()
                                    ->afterStateUpdated(static::persistLeadFieldClosure('assigned_user_id'))
                                    ->extraFieldWrapperAttributes(['class' => 'fi-lead-slideover-paired-field']),
                            ]),
                        Textarea::make('manager_notes')
                            ->label('Внутренние заметки')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('Не показываются клиенту.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(static::persistLeadFieldClosure('manager_notes')),
                    ])
                    ->columns(self::FORM_SECTION_SINGLE_COLUMN),

                Section::make('История действий (Timeline)')
                    ->schema([
                        View::make('filament.tenant.components.lead-timeline'),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                // Sticky bottom actions for mobile drawer
                View::make('filament.tenant.components.lead-sticky-actions')
                    ->visible(fn () => request()->header('sec-ch-ua-mobile') === '?1' || true), // Typically handled by CSS
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordClasses(fn (Lead $record) => match ($record->status) {
                'new' => 'fi-lead-new',
                default => $record->created_at->diffInHours(now()) > 24 && in_array($record->status, ['new', 'in_progress'], true) ? 'fi-lead-stale' : null,
            })
            ->columns([
                ImageColumn::make('motorcycle_thumb')
                    ->label('Фото')
                    ->alignment(Alignment::Center)
                    ->width('3.75rem')
                    ->grow(false)
                    ->getStateUsing(fn (Lead $record): string => FilamentMotorcycleThumbnail::coverUrlOrPlaceholder($record->motorcycle))
                    ->defaultImageUrl(FilamentMotorcycleThumbnail::placeholderDataUrl())
                    ->checkFileExistence(false)
                    ->imageSize(40)
                    ->square()
                    ->verticallyAlignCenter()
                    ->extraImgAttributes([
                        'class' => 'fi-lead-thumb-img rounded-lg object-cover',
                        'loading' => 'lazy',
                        'decoding' => 'async',
                    ])
                    ->extraHeaderAttributes([
                        'class' => 'fi-lead-col-thumb',
                    ], merge: true)
                    ->extraCellAttributes([
                        'class' => 'fi-lead-col-thumb',
                    ], merge: true),
                TextColumn::make('created_at')
                    ->label('Получена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->width('10.5rem')
                    ->grow(false)
                    ->verticallyAlignCenter()
                    ->extraHeaderAttributes(['class' => 'fi-lead-col-received'], merge: true)
                    ->extraCellAttributes(['class' => 'fi-lead-col-received'], merge: true),
                TextColumn::make('motorcycle.name')
                    ->label('Модель')
                    ->sortable()
                    ->placeholder('—')
                    ->description(fn (Lead $record): string => $record->name)
                    ->weight(FontWeight::SemiBold)
                    ->wrap()
                    ->grow()
                    ->verticallyAlignCenter()
                    ->extraHeaderAttributes(['class' => 'fi-lead-col-model'], merge: true)
                    ->extraCellAttributes(['class' => 'fi-lead-col-model'], merge: true),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => PhoneNormalizer::formatForDisplay($state))
                    ->description(function (Lead $record): ?string {
                        $text = app(LeadContactActionResolver::class)->compactSummaryForLead($record, false);
                        if ($text === '') {
                            return null;
                        }

                        return $text;
                    })
                    ->tooltip(fn (Lead $record): ?string => filled(trim((string) $record->phone)) ? $record->phone : null)
                    ->wrap()
                    ->width('11.25rem')
                    ->grow(false)
                    ->verticallyAlignCenter()
                    ->extraHeaderAttributes(['class' => 'fi-lead-col-phone'], merge: true)
                    ->extraCellAttributes(['class' => 'fi-lead-col-phone'], merge: true),
                TextColumn::make('rental_date_from')
                    ->label('Дата с')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('—')
                    ->width('6.75rem')
                    ->grow(false)
                    ->verticallyAlignCenter()
                    ->extraHeaderAttributes(['class' => 'fi-lead-col-date'], merge: true)
                    ->extraCellAttributes(['class' => 'fi-lead-col-date'], merge: true),
                TextColumn::make('rental_date_to')
                    ->label('Дата по')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->width('6.75rem')
                    ->grow(false)
                    ->verticallyAlignCenter()
                    ->extraHeaderAttributes(['class' => 'fi-lead-col-date'], merge: true)
                    ->extraCellAttributes(['class' => 'fi-lead-col-date'], merge: true),
                TextColumn::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn (?string $state): string => Lead::sources()[$state] ?? $state ?? '—')
                    ->badge()
                    ->color('gray')
                    ->wrap()
                    ->width('11.5rem')
                    ->grow(false)
                    ->verticallyAlignCenter()
                    ->extraHeaderAttributes(['class' => 'fi-lead-col-source'], merge: true)
                    ->extraCellAttributes(['class' => 'fi-lead-col-source'], merge: true),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Lead::statuses()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'info',
                        'in_progress' => 'warning',
                        'confirmed', 'completed' => 'success',
                        'cancelled', 'spam' => 'danger',
                        default => 'gray',
                    })
                    ->wrap()
                    ->width('12rem')
                    ->grow(false)
                    ->verticallyAlignCenter()
                    ->extraHeaderAttributes(['class' => 'fi-lead-col-status'], merge: true)
                    ->extraCellAttributes(['class' => 'fi-lead-col-status'], merge: true),
                TextColumn::make('crm_request_id')
                    ->label('CRM')
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? '#'.$state : '—')
                    ->icon(fn (?string $state): ?string => filled($state) ? 'heroicon-o-arrow-top-right-on-square' : null)
                    ->iconPosition(IconPosition::Before)
                    ->placeholder('—')
                    ->alignment(Alignment::Center)
                    ->url(fn (Lead $record): ?string => $record->crm_request_id
                        ? CrmRequestResource::getUrl('view', ['record' => $record->crm_request_id])
                        : null)
                    ->color('gray')
                    ->width('4.25rem')
                    ->grow(false)
                    ->verticallyAlignCenter()
                    ->extraHeaderAttributes(['class' => 'fi-lead-col-crm'], merge: true)
                    ->extraCellAttributes(['class' => 'fi-lead-col-crm'], merge: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(Lead::statuses()),
                SelectFilter::make('source')
                    ->label('Источник')
                    ->options(Lead::sources()),
            ])
            ->recordAction('edit_lead_slide')
            ->recordUrl(null)
            ->recordActionsColumnLabel('Действия')
            ->recordActionsAlignment('end')
            ->recordActions([
                Action::make('mark_contacted')
                    ->iconButton()
                    ->label('Взять в работу')
                    ->tooltip('Взять в работу')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->hidden(fn (Lead $record) => $record->status !== 'new')
                    ->action(function (Lead $record) {
                        $oldStatus = $record->status;
                        $record->update(['status' => 'in_progress']);

                        Notification::make()
                            ->title('Заявка взята в работу')
                            ->success()
                            ->duration(8000)
                            ->actions([
                                Action::make('undo')
                                    ->label('Отменить')
                                    ->button()
                                    ->color('danger')
                                    ->close()
                                    ->action(function () use ($record, $oldStatus) {
                                        $record->update(['status' => $oldStatus]);
                                        LeadActivityLog::create([
                                            'lead_id' => $record->id,
                                            'actor_id' => Auth::id(),
                                            'type' => 'reverted',
                                            'payload' => ['new_status' => $oldStatus],
                                            'comment' => 'Действие отменено (Undo: взятие в работу)',
                                        ]);
                                    }),
                            ])
                            ->send();
                    }),
                Action::make('create_booking_for_lead')
                    ->iconButton()
                    ->label('Бронирование')
                    ->tooltip('Создать бронирование')
                    ->icon('heroicon-o-calendar-days')
                    ->color('primary')
                    ->visible(fn (): bool => Gate::allows('create', Booking::class))
                    ->form(ManualOperatorBookingForm::bookingFromLeadComponents())
                    ->fillForm(fn (Lead $record): array => ManualOperatorBookingForm::bookingFromLeadFormDefaults($record))
                    ->action(function (Lead $record, array $data): void {
                        $tenant = currentTenant();
                        if ($tenant === null) {
                            return;
                        }

                        $start = ManualOperatorBookingForm::toYmd($data['start_date'] ?? null);
                        $end = ManualOperatorBookingForm::toYmd($data['end_date'] ?? null);
                        if ($start === null || $end === null) {
                            throw ValidationException::withMessages([
                                'start_date' => 'Укажите даты бронирования.',
                            ]);
                        }

                        app(ManualLeadBookingService::class)->createManualBooking(new ManualBookingCreateData(
                            tenantId: $tenant->id,
                            name: (string) ($record->name ?? ''),
                            motorcycleId: (int) $data['motorcycle_id'],
                            rentalUnitId: (int) $data['rental_unit_id'],
                            startDateYmd: $start,
                            endDateYmd: $end,
                            phone: $record->phone,
                            email: $record->email,
                            comment: $record->comment,
                            existingLeadId: $record->id,
                            createLead: false,
                            createCrm: false,
                        ));

                        Notification::make()
                            ->title('Бронирование создано')
                            ->success()
                            ->send();
                    }),
                ...static::tenantContactChannelRecordActions(),
                static::leadSlideOverEditAction(),
            ])
            ->extraAttributes(['class' => 'fi-lead-list-table'], merge: true)
            ->emptyStateHeading('Заявок пока нет')
            ->emptyStateDescription('Когда посетители отправят форму на сайте, заявки появятся здесь.')
            ->emptyStateIcon('heroicon-o-inbox');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'new')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * @return list<Action>
     */
    protected static function tenantContactChannelRecordActions(): array
    {
        $types = [
            'call',
            ContactChannelType::Whatsapp->value,
            ContactChannelType::Telegram->value,
            ContactChannelType::Vk->value,
            ContactChannelType::Max->value,
        ];
        $actions = [];
        foreach ($types as $type) {
            $actions[] = Action::make('cc_'.$type)
                ->iconButton()
                ->label(fn (Lead $record): string => static::leadContactDescriptor($record, $type)['label'] ?? '')
                ->tooltip(fn (Lead $record): string => static::leadContactDescriptor($record, $type)['tooltip'] ?? '')
                ->icon(fn (Lead $record): string => static::leadContactDescriptor($record, $type)['icon'] ?? 'heroicon-o-link')
                ->color(fn (Lead $record): string => static::leadContactDescriptor($record, $type)['color'] ?? 'gray')
                ->url(fn (Lead $record): string => static::leadContactActionUrl($record, $type) ?? '#')
                ->openUrlInNewTab(fn (Lead $record): bool => (bool) (static::leadContactDescriptor($record, $type)['open_in_new_tab'] ?? false))
                ->visible(fn (Lead $record): bool => static::leadContactActionUrl($record, $type) !== null);
        }

        return $actions;
    }

    /**
     * @return ?array{type: string, label: string, url: string, icon: string, color: string, open_in_new_tab: bool, is_preferred: bool, tooltip: string}
     */
    protected static function leadContactDescriptor(Lead $lead, string $type): ?array
    {
        static $cache = [];
        $id = (int) $lead->getKey();
        if ($id > 0 && ! isset($cache[$id])) {
            $cache[$id] = app(LeadContactActionResolver::class)->orderedActionsForLead($lead);
        }
        $list = $id > 0 ? ($cache[$id] ?? []) : app(LeadContactActionResolver::class)->orderedActionsForLead($lead);
        foreach ($list as $d) {
            if (($d['type'] ?? '') === $type) {
                return $d;
            }
        }

        return null;
    }

    protected static function leadContactActionUrl(Lead $record, string $type): ?string
    {
        $d = static::leadContactDescriptor($record, $type);

        return isset($d['url']) && $d['url'] !== '' && $d['url'] !== '#' ? (string) $d['url'] : null;
    }
}
