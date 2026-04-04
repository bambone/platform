<?php

namespace App\Filament\Tenant\Resources;

use App\Auth\AccessRoles;
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
use App\Terminology\DomainTermKeys;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
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
use Illuminate\Validation\ValidationException;
use UnitEnum;

class LeadResource extends Resource
{
    use ResolvesDomainTermLabels;

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
        return EditAction::make()
            ->slideOver()
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
                    ->description('Как с вами связался потенциальный клиент.')
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
                        TextInput::make('messenger')
                            ->label('Мессенджер')
                            ->maxLength(255)
                            ->helperText('Ник или способ связи, если указан.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(static::persistLeadFieldClosure('messenger')),
                    ])->columns(2),

                Section::make('Детали заявки')
                    ->description('Интерес к технике и даты; помогает менеджеру подготовить ответ.')
                    ->schema([
                        Select::make('motorcycle_id')
                            ->label('Интерес к карточке каталога')
                            ->relationship('motorcycle', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Можно оставить пустым, если заявка общая.')
                            ->live()
                            ->afterStateUpdated(static::persistLeadFieldClosure('motorcycle_id')),
                        DatePicker::make('rental_date_from')
                            ->label('Дата начала аренды')
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(static::persistLeadFieldClosure('rental_date_from')),
                        DatePicker::make('rental_date_to')
                            ->label('Дата окончания аренды')
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(static::persistLeadFieldClosure('rental_date_to')),
                        Select::make('source')
                            ->label('Источник')
                            ->options(Lead::sources())
                            ->helperText('Откуда пришла заявка: форма на сайте, звонок и т.д.')
                            ->live()
                            ->afterStateUpdated(static::persistLeadFieldClosure('source')),
                        Textarea::make('comment')
                            ->label('Комментарий клиента')
                            ->rows(3)
                            ->live(onBlur: true)
                            ->afterStateUpdated(static::persistLeadFieldClosure('comment')),
                    ])->columns(2),

                Section::make('Работа менеджера')
                    ->description('Видно только в кабинете; на сайт не выводится.')
                    ->schema([
                        Select::make('status')
                            ->label('Статус заявки')
                            ->options(Lead::statuses())
                            ->required()
                            ->live()
                            ->afterStateUpdated(static::persistLeadFieldClosure('status'))
                            ->helperText('Новая — ещё не обработана. В работе — менеджер связался. Завершена/отменена фиксируют исход.'),
                        Select::make('assigned_user_id')
                            ->label('Ответственный')
                            ->relationship(
                                'assignedUser',
                                'name',
                                modifyQueryUsing: function (Builder $query, ?string $search, Select $component): void {
                                    $tenant = currentTenant();
                                    if ($tenant === null) {
                                        $query->whereRaw('1 = 0');

                                        return;
                                    }

                                    $record = $component->getRecord();
                                    $currentAssigneeId = $record instanceof Lead ? $record->assigned_user_id : null;

                                    $query->where(function (Builder $inner) use ($tenant, $currentAssigneeId): void {
                                        $inner->whereHas('tenants', function (Builder $tq) use ($tenant): void {
                                            $tq->where('tenants.id', $tenant->id)
                                                ->where('tenant_user.status', 'active')
                                                ->whereIn('tenant_user.role', AccessRoles::tenantMembershipRolesForPanel());
                                        });
                                        if ($currentAssigneeId !== null) {
                                            $inner->orWhere(
                                                $inner->getModel()->getQualifiedKeyName(),
                                                $currentAssigneeId,
                                            );
                                        }
                                    });
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('Кто ведёт заявку внутри команды.')
                            ->live()
                            ->afterStateUpdated(static::persistLeadFieldClosure('assigned_user_id')),
                        Textarea::make('manager_notes')
                            ->label('Внутренние заметки')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('Не показываются клиенту.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(static::persistLeadFieldClosure('manager_notes')),
                    ])->columns(2),

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
            ->recordAction(EditAction::class)
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
                            messenger: $record->messenger,
                            existingLeadId: $record->id,
                            createLead: false,
                            createCrm: false,
                        ));

                        Notification::make()
                            ->title('Бронирование создано')
                            ->success()
                            ->send();
                    }),
                Action::make('whatsapp')
                    ->iconButton()
                    ->label('Написать в WhatsApp')
                    ->tooltip('Написать в WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->url(fn (Lead $record) => 'https://wa.me/'.preg_replace('/[^0-9]/', '', $record->phone).'?text='.urlencode('Здравствуйте! Пишу по поводу вашей заявки на аренду...'))
                    ->openUrlInNewTab(),
                Action::make('call')
                    ->iconButton()
                    ->label('Позвонить')
                    ->tooltip('Позвонить')
                    ->icon('heroicon-o-phone')
                    ->color('gray')
                    ->url(fn (Lead $record) => 'tel:'.preg_replace('/[^0-9]/', '', $record->phone)),
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
}
