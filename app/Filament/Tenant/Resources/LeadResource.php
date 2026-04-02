<?php

namespace App\Filament\Tenant\Resources;

use App\Auth\AccessRoles;
use App\Filament\Tenant\Concerns\ResolvesDomainTermLabels;
use App\Filament\Tenant\Resources\LeadResource\Pages;
use App\Models\Lead;
use App\Models\LeadActivityLog;
use App\Support\FilamentMotorcycleThumbnail;
use App\Terminology\DomainTermKeys;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
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
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->required()
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('messenger')
                            ->label('Мессенджер')
                            ->maxLength(255)
                            ->helperText('Ник или способ связи, если указан.'),
                    ])->columns(2),

                Section::make('Детали заявки')
                    ->description('Интерес к технике и даты; помогает менеджеру подготовить ответ.')
                    ->schema([
                        Select::make('motorcycle_id')
                            ->label('Интерес к карточке каталога')
                            ->relationship('motorcycle', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Можно оставить пустым, если заявка общая.'),
                        DatePicker::make('rental_date_from')
                            ->label('Дата начала аренды')
                            ->native(false),
                        DatePicker::make('rental_date_to')
                            ->label('Дата окончания аренды')
                            ->native(false),
                        Select::make('source')
                            ->label('Источник')
                            ->options(Lead::sources())
                            ->helperText('Откуда пришла заявка: форма на сайте, звонок и т.д.'),
                        Textarea::make('comment')
                            ->label('Комментарий клиента')
                            ->rows(3),
                    ])->columns(2),

                Section::make('Работа менеджера')
                    ->description('Видно только в кабинете; на сайт не выводится.')
                    ->schema([
                        Select::make('status')
                            ->label('Статус заявки')
                            ->options(Lead::statuses())
                            ->required()
                            ->live()
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
                                            $inner->orWhereKey($currentAssigneeId);
                                        }
                                    });
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('Кто ведёт заявку внутри команды.'),
                        Textarea::make('manager_notes')
                            ->label('Внутренние заметки')
                            ->rows(4)
                            ->helperText('Не показываются клиенту.'),
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
                    ->label('')
                    ->getStateUsing(fn (Lead $record): string => FilamentMotorcycleThumbnail::coverUrlOrPlaceholder($record->motorcycle))
                    ->defaultImageUrl(FilamentMotorcycleThumbnail::placeholderDataUrl())
                    ->checkFileExistence(false)
                    ->imageSize(48)
                    ->square()
                    ->extraImgAttributes([
                        'class' => 'rounded-lg object-cover',
                        'loading' => 'lazy',
                        'decoding' => 'async',
                    ])
                    ->extraCellAttributes(['class' => 'w-px pe-0']),
                TextColumn::make('created_at')
                    ->label('Получена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('motorcycle.name')
                    ->label('Модель')
                    ->sortable()
                    ->placeholder('—')
                    ->description(fn (Lead $record): string => $record->name)
                    ->wrap(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('rental_date_from')
                    ->label('С')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('rental_date_to')
                    ->label('По')
                    ->date('d.m.Y')
                    ->placeholder('—'),
                TextColumn::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn (?string $state): string => Lead::sources()[$state] ?? $state ?? '—')
                    ->badge(),
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
                    }),
                TextColumn::make('crm_request_id')
                    ->label('CRM')
                    ->sortable()
                    ->placeholder('—')
                    ->url(fn (Lead $record): ?string => $record->crm_request_id
                        ? CrmRequestResource::getUrl('view', ['record' => $record->crm_request_id])
                        : null)
                    ->color('primary'),
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
            ->actions([
                Action::make('mark_contacted')
                    ->label('В работу')
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
                Action::make('whatsapp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->label('WA')
                    ->url(fn (Lead $record) => 'https://wa.me/'.preg_replace('/[^0-9]/', '', $record->phone).'?text='.urlencode('Здравствуйте! Пишу по поводу вашей заявки на аренду...'))
                    ->openUrlInNewTab(),
                Action::make('call')
                    ->icon('heroicon-o-phone')
                    ->color('gray')
                    ->label('Call')
                    ->url(fn (Lead $record) => 'tel:'.preg_replace('/[^0-9]/', '', $record->phone)),
                EditAction::make()->slideOver(),
            ])
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
