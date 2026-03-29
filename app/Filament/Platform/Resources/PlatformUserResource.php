<?php

namespace App\Filament\Platform\Resources;

use App\Auth\AccessRoles;
use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\PlatformUserResource\Pages;
use App\Filament\Support\RoleLabels;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class PlatformUserResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Сотрудники платформы';

    protected static ?string $modelLabel = 'Пользователь';

    protected static ?string $pluralModelLabel = 'Сотрудники платформы';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $panel = 'platform';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', AccessRoles::platformRoles()));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Профиль')
                    ->description(
                        'Сотрудник входит только в **консоль платформы** (`/platform`). '.
                        '**Участников кабинета клиента** (вход на `/admin` у домена клиента) заводите не здесь, а в **Клиенты** → открыть клиента → вкладка **«Команда клиента»**. '.
                        'Набор прав pivot-ролей в кабинете — страница **Платформа → Безопасность и роли кабинета**.'
                    )
                    ->schema([
                        TextInput::make('name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Используется для входа в консоль платформы.'),
                        TextInput::make('password')
                            ->label('Пароль')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255)
                            ->helperText(fn (string $operation): string => $operation === 'edit'
                                ? 'Оставьте пустым, чтобы не менять пароль.'
                                : ''),
                        Select::make('status')
                            ->label('Статус учётной записи')
                            ->options(User::statuses())
                            ->default('active')
                            ->required()
                            ->helperText('Заблокированный пользователь не сможет войти ни в одну панель.'),
                        CheckboxList::make('platform_roles')
                            ->label('Роли в консоли платформы')
                            ->options(RoleLabels::platformRoleOptions())
                            ->descriptions(RoleLabels::platformRoleDescriptions())
                            ->required()
                            ->columns(1)
                            ->bulkToggleable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (User::statuses()[$state] ?? $state) : '—'),
                TextColumn::make('platform_roles_list')
                    ->label('Роли')
                    ->getStateUsing(fn (User $record): string => RoleLabels::formatPlatformRolesList(
                        $record->roles->whereIn('name', AccessRoles::platformRoles())->pluck('name')->all()
                    )),
            ])
            ->actions([EditAction::make()])
            ->emptyStateHeading('Сотрудников пока нет')
            ->emptyStateDescription(
                'Здесь только пользователи с ролями консоли платформы. '.
                'Для доступа к сайту клиента: **Клиенты** → карточка клиента → **Команда клиента**.'
            )
            ->emptyStateIcon('heroicon-o-users');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformUsers::route('/'),
            'create' => Pages\CreatePlatformUser::route('/create'),
            'edit' => Pages\EditPlatformUser::route('/{record}/edit'),
        ];
    }
}
