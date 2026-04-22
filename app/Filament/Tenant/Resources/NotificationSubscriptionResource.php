<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Shared\Lifecycle\AdminFilamentDelete;
use App\Filament\Support\AdminEmptyState;
use App\Filament\Tenant\Resources\NotificationSubscriptionResource\Pages;
use App\Models\NotificationDestination;
use App\Models\NotificationSubscription;
use App\NotificationCenter\NotificationEventRegistry;
use App\NotificationCenter\NotificationSeverity;
use App\Rules\ValidNotificationSubscriptionEventKey;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class NotificationSubscriptionResource extends Resource
{
    protected static ?string $model = NotificationSubscription::class;

    protected static ?string $navigationLabel = 'Правила уведомлений';

    protected static ?string $modelLabel = 'Правило';

    protected static ?string $pluralModelLabel = 'Правила';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 26;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    public static function canAccess(): bool
    {
        return Gate::allows('manage_notifications') || Gate::allows('manage_notification_subscriptions');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = currentTenant();
        $query = parent::getEloquentQuery();
        if ($tenant === null) {
            return $query->whereRaw('1 = 0');
        }

        $query = $query->where('tenant_id', $tenant->id);

        if (! Gate::allows('manage_notifications')) {
            return $query->where('user_id', Auth::id());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        $severities = [];
        foreach (NotificationSeverity::cases() as $c) {
            $severities[$c->value] = $c->name;
        }

        return $schema->components([
            Section::make('Правило')
                ->description(
                    'Связка «событие в системе → куда отправить». Когда событие происходит (например, новая заявка), '
                    .'уведомление уходит на отмеченных получателей, если правило включено. Список каналов (email, Telegram, колокольчик в кабинете и т.д.) '
                    .'задаётся в разделе «Получатели уведомлений» — здесь вы только подключаете их к этому правилу.'
                )
                ->schema([
                    TextInput::make('name')->label('Название')->required()->maxLength(255),
                    Select::make('event_key')
                        ->label('Событие')
                        ->options(NotificationEventRegistry::optionsForFilament())
                        ->helperText(
                            '«Все уведомления» — одно правило на все текущие и будущие типы событий. '
                            .'По-прежнему учитываются «Мин. важность», условия, расписание и дедуп доставок по получателю. '
                            .'Для одного и того же получателя точное правило (конкретное событие) имеет приоритет над «Все уведомления».'
                        )
                        ->rules([new ValidNotificationSubscriptionEventKey])
                        ->required()
                        ->native(true)
                        ->searchable(false),
                    Toggle::make('enabled')->label('Включено')->default(true),
                    Select::make('severity_min')
                        ->label('Мин. важность')
                        ->options($severities)
                        ->native(true),
                    CheckboxList::make('destination_ids')
                        ->label('Получатели')
                        ->options(function (): array {
                            $tenant = currentTenant();
                            if ($tenant === null) {
                                return [];
                            }

                            $q = NotificationDestination::query()
                                ->where('tenant_id', $tenant->id);
                            if (! Gate::allows('manage_notifications')) {
                                $q->where('user_id', Auth::id());
                            }

                            return $q->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->columns(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return AdminEmptyState::applyInitial(
            $table
                ->columns([
                    TextColumn::make('name')->label('Название'),
                    TextColumn::make('event_key')
                        ->label('Событие')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => NotificationEventRegistry::labelForEventKeyInUi((string) $state)),
                    IconColumn::make('enabled')->label('Вкл.')->boolean(),
                ])
                ->recordActions([
                    EditAction::make(),
                    AdminFilamentDelete::configureTableDeleteAction(
                        DeleteAction::make(),
                        ['entry' => 'filament.tenant.notification_subscription.table'],
                    ),
                ])
                ->defaultSort('id', 'desc'),
            'Правил уведомлений пока нет',
            'Создайте правило: какое событие и кому доставлять. Если списка получателей не хватает — сначала добавьте их в разделе «Получатели уведомлений».',
            'heroicon-o-bell-alert',
            [CreateAction::make()->label('Создать правило')],
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationSubscriptions::route('/'),
            'create' => Pages\CreateNotificationSubscription::route('/create'),
            'edit' => Pages\EditNotificationSubscription::route('/{record}/edit'),
        ];
    }
}
