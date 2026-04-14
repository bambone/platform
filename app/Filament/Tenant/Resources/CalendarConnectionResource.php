<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\CalendarConnectionResource\Pages;
use App\Filament\Tenant\Resources\CalendarConnectionResource\RelationManagers\CalendarSubscriptionsRelationManager;
use App\Filament\Tenant\Support\CalendarConnectionFormGuide;
use App\Jobs\Scheduling\SyncCalendarBusyJob;
use App\Models\CalendarConnection;
use App\Scheduling\Enums\CalendarAccessMode;
use App\Scheduling\Enums\CalendarProviderType;
use App\Scheduling\Enums\SchedulingScope;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use App\Filament\Tenant\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use UnitEnum;

class CalendarConnectionResource extends Resource
{
    protected static ?string $model = CalendarConnection::class;

    protected static ?string $navigationLabel = 'Календари (подключения)';

    protected static ?string $modelLabel = 'Календарное подключение';

    protected static ?string $pluralModelLabel = 'Подключения календарей';

    protected static string|UnitEnum|null $navigationGroup = 'Scheduling';

    protected static ?int $navigationSort = 60;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

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
            ->where('tenant_id', currentTenant()?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $providerOptions = [];
        foreach (CalendarProviderType::cases() as $c) {
            $providerOptions[$c->value] = $c->label();
        }

        return $schema
            ->components([
                Section::make('Шаг 1. Провайдер и способ входа')
                    ->description('Сначала выберите систему календаря и способ авторизации — ниже появятся инструкции и подписи полей под ваш сценарий.')
                    ->schema([
                        Select::make('provider')
                            ->label('Провайдер календаря')
                            ->options($providerOptions)
                            ->required()
                            ->native()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                $p = CalendarProviderType::tryFrom((string) ($state ?? ''));
                                if ($p === null) {
                                    return;
                                }
                                $allowed = array_map(
                                    static fn (CalendarAccessMode $m) => $m->value,
                                    CalendarAccessMode::orderedForForm($p),
                                );
                                $current = $get('access_mode');
                                if ($current === null || $current === '' || ! in_array($current, $allowed, true)) {
                                    $set('access_mode', $allowed[0] ?? null);
                                }
                            }),
                        Select::make('access_mode')
                            ->label('Режим доступа')
                            ->options(function (Get $get): array {
                                $p = CalendarProviderType::tryFrom((string) ($get('provider') ?? ''));
                                if ($p === null) {
                                    return [];
                                }
                                $out = [];
                                foreach (CalendarAccessMode::orderedForForm($p) as $mode) {
                                    $out[$mode->value] = $mode->label();
                                }

                                return $out;
                            })
                            ->required()
                            ->native()
                            ->live(),
                        Placeholder::make('instructions_panel')
                            ->label('')
                            ->content(function (Get $get): HtmlString {
                                return CalendarConnectionFormGuide::panel(
                                    is_string($get('provider')) ? $get('provider') : null,
                                    is_string($get('access_mode')) ? $get('access_mode') : null,
                                );
                            })
                            ->columnSpanFull(),
                    ]),
                Section::make('Шаг 2. Параметры подключения')
                    ->schema([
                        TextInput::make('display_name')
                            ->label('Название в списке')
                            ->maxLength(255)
                            ->helperText('Как вы увидите это подключение в админке (например «Google — Иван»).'),
                        TextInput::make('account_email')
                            ->label('Аккаунт (email)')
                            ->email()
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => self::shouldShowAccountEmail($get))
                            ->required(fn (Get $get): bool => self::isAccountEmailRequired($get))
                            ->helperText(fn (Get $get): ?string => self::accountEmailHelper($get)),
                        Select::make('scheduling_resource_id')
                            ->label('Привязать к календарному ресурсу')
                            ->relationship(
                                'schedulingResource',
                                'label',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('scheduling_scope', SchedulingScope::Tenant)
                                    ->where('tenant_id', currentTenant()?->id)
                                    ->orderBy('label'),
                            )
                            ->native()
                            ->nullable()
                            ->helperText('Необязательно: если ресурс выбран, подключение логически закрепляется за сотрудником/ресурсом из раздела «Ресурсы записи».'),
                        Grid::make(1)
                            ->schema([
                                Textarea::make('credentials_encrypted')
                                    ->label(fn (Get $get): string => self::credentialsLabel($get))
                                    ->rows(fn (Get $get): int => self::credentialsRows($get))
                                    ->helperText(fn (Get $get): string => CalendarConnectionFormGuide::credentialsFieldHelper(
                                        is_string($get('provider')) ? $get('provider') : null,
                                        is_string($get('access_mode')) ? $get('access_mode') : null,
                                    ))
                                    ->required(fn (Get $get): bool => self::areCredentialsRequired($get))
                                    ->columnSpanFull(),
                            ]),
                        Select::make('status')
                            ->label('Статус')
                            ->options([
                                'active' => 'Активно',
                                'paused' => 'На паузе',
                                'error' => 'Ошибка (проверьте учётные данные)',
                            ])
                            ->default('active')
                            ->required()
                            ->native(),
                        Toggle::make('is_active')
                            ->label('Учитывать при синхронизации')
                            ->helperText('Выключите, чтобы временно не опрашивать этот календарь, не удаляя запись.')
                            ->default(true),
                        TextInput::make('stale_after_seconds')
                            ->label('Считать busy устаревшим через, сек')
                            ->numeric()
                            ->minValue(60)
                            ->nullable()
                            ->helperText('Пусто — значение по умолчанию в коде. Например 3600 = 1 час; после этого слоты могут трактоваться как «нет свежих данных».'),
                    ]),
            ]);
    }

    private static function shouldShowAccountEmail(Get $get): bool
    {
        $p = CalendarProviderType::tryFrom((string) ($get('provider') ?? ''));
        $m = CalendarAccessMode::tryFrom((string) ($get('access_mode') ?? ''));
        if ($p === null || $m === null) {
            return true;
        }

        if ($p === CalendarProviderType::Google && $m === CalendarAccessMode::ServiceToken) {
            return false;
        }

        return true;
    }

    private static function isAccountEmailRequired(Get $get): bool
    {
        if (! self::shouldShowAccountEmail($get)) {
            return false;
        }

        $m = CalendarAccessMode::tryFrom((string) ($get('access_mode') ?? ''));

        return $m !== CalendarAccessMode::ServiceToken;
    }

    private static function accountEmailHelper(Get $get): ?string
    {
        if (! self::shouldShowAccountEmail($get)) {
            return null;
        }

        $p = CalendarProviderType::tryFrom((string) ($get('provider') ?? ''));
        $m = CalendarAccessMode::tryFrom((string) ($get('access_mode') ?? ''));
        if ($p === null || $m === null) {
            return 'Email учётной записи календаря (тот же, что вход в почту/календарь).';
        }

        return match ([$p, $m]) {
            [CalendarProviderType::Google, CalendarAccessMode::Oauth] => 'Google-аккаунт, для которого выдаются токены.',
            [CalendarProviderType::Google, CalendarAccessMode::AppPassword] => 'Полный адрес Gmail / Google Workspace.',
            [CalendarProviderType::Yandex, CalendarAccessMode::AppPassword],
            [CalendarProviderType::Yandex, CalendarAccessMode::Oauth] => 'Логин Яндекса (как правило login@yandex.ru или @ya.ru).',
            [CalendarProviderType::Mailru, CalendarAccessMode::AppPassword],
            [CalendarProviderType::Mailru, CalendarAccessMode::Oauth] => 'Адрес почты Mail.ru для календаря.',
            default => 'Укажите email, если он идентифицирует календарь в этом режиме.',
        };
    }

    private static function credentialsLabel(Get $get): string
    {
        return match (CalendarAccessMode::tryFrom((string) ($get('access_mode') ?? ''))) {
            CalendarAccessMode::Oauth => 'Секреты OAuth (JSON с токенами)',
            CalendarAccessMode::AppPassword => 'Пароль или пароль приложения',
            CalendarAccessMode::ServiceToken => 'Сервисный ключ / JSON / токен API',
            default => 'Учётные данные',
        };
    }

    private static function credentialsRows(Get $get): int
    {
        return match (CalendarAccessMode::tryFrom((string) ($get('access_mode') ?? ''))) {
            CalendarAccessMode::AppPassword => 2,
            default => 8,
        };
    }

    private static function areCredentialsRequired(Get $get): bool
    {
        $m = CalendarAccessMode::tryFrom((string) ($get('access_mode') ?? ''));

        return match ($m) {
            CalendarAccessMode::AppPassword, CalendarAccessMode::ServiceToken => true,
            default => false,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->label('Название')->placeholder('—'),
                TextColumn::make('provider')
                    ->label('Провайдер')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof CalendarProviderType) {
                            return $state->label();
                        }

                        return CalendarProviderType::tryFrom((string) $state)?->label() ?? (string) $state;
                    }),
                TextColumn::make('access_mode')
                    ->label('Доступ')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof CalendarAccessMode) {
                            return $state->label();
                        }

                        return CalendarAccessMode::tryFrom((string) $state)?->label() ?? (string) $state;
                    })
                    ->toggleable(),
                TextColumn::make('account_email')->label('Аккаунт')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label('Вкл.')->boolean(),
            ])
            ->actions([
                Action::make('syncBusy')
                    ->label('Синхр. busy')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (CalendarConnection $record): void {
                        $start = now('UTC')->subDay();
                        $end = now('UTC')->addDays(60);
                        SyncCalendarBusyJob::dispatch(
                            (int) $record->id,
                            $start->toIso8601String(),
                            $end->toIso8601String(),
                        );
                        Notification::make()
                            ->title('Синхронизация поставлена в очередь')
                            ->body('Диапазон: '.$start->toDateString().' — '.$end->toDateString().' (UTC).')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CalendarSubscriptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCalendarConnections::route('/'),
            'create' => Pages\CreateCalendarConnection::route('/create'),
            'edit' => Pages\EditCalendarConnection::route('/{record}/edit'),
        ];
    }
}
