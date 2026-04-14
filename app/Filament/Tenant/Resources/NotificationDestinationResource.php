<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\NotificationDestinationResource\Pages;
use App\Models\NotificationDestination;
use App\NotificationCenter\NotificationChannelType;
use App\NotificationCenter\NotificationDestinationStatus;
use App\Tenant\Filament\TenantCabinetUserPicker;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Tenant\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class NotificationDestinationResource extends Resource
{
    protected static ?string $model = NotificationDestination::class;

    protected static ?string $panel = 'admin';

    protected static ?string $navigationLabel = 'Получатели уведомлений';

    protected static ?string $modelLabel = 'Получатель';

    protected static ?string $pluralModelLabel = 'Получатели';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 25;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    public static function canAccess(): bool
    {
        return Gate::allows('manage_notifications') || Gate::allows('manage_notification_destinations');
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = currentTenant();
        $query = parent::getEloquentQuery();
        if ($tenant === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('tenant_id', $tenant->id);
    }

    public static function form(Schema $schema): Schema
    {
        $types = [];
        foreach (NotificationChannelType::cases() as $c) {
            $types[$c->value] = $c->label();
        }

        $statuses = [];
        foreach (NotificationDestinationStatus::cases() as $c) {
            $statuses[$c->value] = $c->name;
        }

        return $schema->components([
            Section::make('Получатель')
                ->schema([
                    TextInput::make('name')->label('Название')->required()->maxLength(255),
                    Select::make('type')
                        ->label('Тип канала')
                        ->options($types)
                        ->required()
                        ->native(true),
                    Select::make('status')
                        ->label('Статус')
                        ->options($statuses)
                        ->required()
                        ->native(true)
                        ->default(NotificationDestinationStatus::Draft->value),
                    Toggle::make('is_shared')
                        ->label('Общий для кабинета')
                        ->default(false),
                    Select::make('user_id')
                        ->label('Пользователь (персональный)')
                        ->options(function (Select $component): array {
                            $tenantId = currentTenant()?->id;
                            $record = $component->getRecord();
                            $legacyUserId = ($record instanceof NotificationDestination && $record->exists && $record->user_id !== null)
                                ? (int) $record->user_id
                                : null;

                            return TenantCabinetUserPicker::nameOptionsForCabinet($tenantId, $legacyUserId);
                        })
                        ->searchable(false)
                        ->preload()
                        ->native(true),
                    KeyValue::make('config_json')
                        ->label('Конфиг канала')
                        ->keyLabel('Ключ')
                        ->valueLabel('Значение')
                        ->helperText('Например email, chat_id, url, secret для webhook.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Название'),
                TextColumn::make('type')->label('Тип')->badge(),
                TextColumn::make('status')->label('Статус')->badge(),
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
            'index' => Pages\ListNotificationDestinations::route('/'),
            'create' => Pages\CreateNotificationDestination::route('/create'),
            'edit' => Pages\EditNotificationDestination::route('/{record}/edit'),
        ];
    }
}
