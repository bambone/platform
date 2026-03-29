<?php

namespace App\Filament\Platform\Resources\TenantResource\RelationManagers;

use App\Auth\AccessRoles;
use App\Filament\Platform\Resources\PlatformUserResource;
use App\Filament\Support\RoleLabels;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class TenantUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Команда клиента';

    protected static bool $shouldSkipAuthorization = true;

    /**
     * @return array<string, string>
     */
    private static function pivotStatusOptions(): array
    {
        return [
            'active' => 'Активен',
            'invited' => 'Приглашён',
            'suspended' => 'Приостановлен',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Учётная запись')
                    ->schema([
                        TextInput::make('name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(255)
                            ->hiddenOn('edit'),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->hiddenOn('edit'),
                        TextInput::make('password')
                            ->label('Пароль')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255)
                            ->hiddenOn('edit'),
                        Select::make('account_status')
                            ->label('Статус учётной записи')
                            ->options(User::statuses())
                            ->default('active')
                            ->required()
                            ->hiddenOn('edit')
                            ->helperText('Для входа в кабинет участнику также нужен активный статус в команде клиента.'),
                    ]),
                Section::make('Роль в команде клиента')
                    ->schema([
                        Select::make('role')
                            ->label('Роль в кабинете')
                            ->options(RoleLabels::tenantMembershipRoleOptions())
                            ->required(),
                        Select::make('membership_status')
                            ->label('Статус в команде')
                            ->options(self::pivotStatusOptions())
                            ->default('active')
                            ->required(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        $tenant = $this->getOwnerRecord();
        $adminUrl = $tenant instanceof Tenant ? $tenant->cabinetAdminUrl() : null;

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('roles'))
            ->recordTitleAttribute('name')
            ->description(
                $adminUrl !== null
                    ? new HtmlString(
                        'Кабинет клиента: <a class="text-primary-600 underline font-medium" href="'.e($adminUrl).'" target="_blank" rel="noopener noreferrer">'.e($adminUrl).'</a>'
                    )
                    : 'Добавьте активный домен клиенту, чтобы получить прямую ссылку на кабинет (/admin).'
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('pivot.role')
                    ->label('Роль в клиенте')
                    ->formatStateUsing(fn (?string $state): string => $state ? (RoleLabels::tenantMembershipRoleOptions()[$state] ?? $state) : '—'),
                TextColumn::make('pivot.status')
                    ->label('Статус в команде')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (self::pivotStatusOptions()[$state] ?? $state) : '—')
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'invited' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('platform_access')
                    ->label('Платформа')
                    ->badge()
                    ->getStateUsing(function (User $record): string {
                        return $record->roles->whereIn('name', AccessRoles::platformRoles())->isNotEmpty() ? 'Да' : 'Нет';
                    })
                    ->color(fn (string $state): string => $state === 'Да' ? 'info' : 'gray'),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->label('Создать пользователя')
                    ->using(function (array $data): Model {
                        /** @var Tenant $tenant */
                        $tenant = $this->getOwnerRecord();

                        $user = User::query()->create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'password' => $data['password'],
                            'status' => $data['account_status'] ?? 'active',
                        ]);

                        $tenant->users()->attach($user->id, [
                            'role' => $data['role'],
                            'status' => $data['membership_status'] ?? 'active',
                        ]);

                        return $user;
                    }),
                AttachAction::make()
                    ->label('Добавить существующего')
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('role')
                            ->label('Роль в кабинете')
                            ->options(RoleLabels::tenantMembershipRoleOptions())
                            ->required(),
                        Select::make('status')
                            ->label('Статус в команде')
                            ->options(self::pivotStatusOptions())
                            ->default('active')
                            ->required(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Роль и статус')
                    ->modalHeading('Роль и статус в команде')
                    ->schema([
                        Select::make('role')
                            ->label('Роль в кабинете')
                            ->options(RoleLabels::tenantMembershipRoleOptions())
                            ->required(),
                        Select::make('status')
                            ->label('Статус в команде')
                            ->options(self::pivotStatusOptions())
                            ->required(),
                    ])
                    ->before(function (EditAction $action): void {
                        /** @var User $record */
                        $record = $action->getRecord();
                        /** @var Tenant $tenant */
                        $tenant = $this->getOwnerRecord();
                        $data = $action->getData();
                        $oldRole = $record->pivot->role ?? null;
                        $newRole = $data['role'] ?? null;

                        if ($oldRole === 'tenant_owner' && $newRole !== 'tenant_owner') {
                            $ownerCount = $tenant->users()->wherePivot('role', 'tenant_owner')->count();
                            if ($ownerCount <= 1) {
                                throw ValidationException::withMessages([
                                    'role' => 'Нельзя снять роль владельца, пока в команде нет другого владельца клиента.',
                                ]);
                            }
                        }
                    }),
                Action::make('openPlatformUser')
                    ->label('В сотрудниках платформы')
                    ->icon(Heroicon::ArrowTopRightOnSquare)
                    ->url(fn (User $record): ?string => $record->roles->whereIn('name', AccessRoles::platformRoles())->isNotEmpty()
                        ? PlatformUserResource::getUrl('edit', ['record' => $record])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (User $record): bool => $record->roles->whereIn('name', AccessRoles::platformRoles())->isNotEmpty()),
                DetachAction::make()
                    ->label('Отвязать')
                    ->modalHeading('Отвязать от клиента?')
                    ->modalDescription('Пользователь останется в системе, но потеряет доступ к этому кабинету. Если это последний владелец клиента, сначала назначьте другого владельца или добавьте нового.')
                    ->before(function (DetachAction $action): void {
                        /** @var User $record */
                        $record = $action->getRecord();
                        /** @var Tenant $tenant */
                        $tenant = $this->getOwnerRecord();

                        if (($record->pivot->role ?? null) !== 'tenant_owner') {
                            return;
                        }

                        $ownerCount = $tenant->users()->wherePivot('role', 'tenant_owner')->count();
                        if ($ownerCount <= 1) {
                            throw ValidationException::withMessages([
                                'recordId' => 'Нельзя отвязать последнего владельца клиента без замены.',
                            ]);
                        }
                    }),
            ])
            ->defaultSort('users.name');
    }
}
