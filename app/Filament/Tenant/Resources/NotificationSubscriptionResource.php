<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\NotificationSubscriptionResource\Pages;
use App\Models\NotificationDestination;
use App\Models\NotificationSubscription;
use App\NotificationCenter\NotificationEventRegistry;
use App\NotificationCenter\NotificationSeverity;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Tenant\Resources\Resource;
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

    protected static ?string $panel = 'admin';

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
                ->schema([
                    TextInput::make('name')->label('Название')->required()->maxLength(255),
                    Select::make('event_key')
                        ->label('Событие')
                        ->options(NotificationEventRegistry::optionsForFilament())
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
        return $table
            ->columns([
                TextColumn::make('name')->label('Название'),
                TextColumn::make('event_key')->label('Событие')->badge(),
                IconColumn::make('enabled')->label('Вкл.')->boolean(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc');
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
