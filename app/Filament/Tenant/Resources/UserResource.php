<?php

namespace App\Filament\Tenant\Resources;

use App\Auth\TenantMembershipRoleHierarchy;
use App\Filament\Support\FilamentInlineMarkdown;
use App\Filament\Support\RoleLabels;
use App\Filament\Support\UserPasswordFormFields;
use App\Filament\Tenant\Resources\UserResource\Pages;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Tenant\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Команда';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 20;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $modelLabel = 'Пользователь';

    protected static ?string $pluralModelLabel = 'Команда';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        $tenant = currentTenant();
        $query = parent::getEloquentQuery();

        if ($tenant === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas('tenants', fn (Builder $q) => $q->where('tenants.id', $tenant->id));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Профиль')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                        UserPasswordFormFields::createPasswordInput()
                            ->hiddenOn('edit'),
                        Select::make('status')
                            ->label('Статус учётной записи')
                            ->options(User::statuses())
                            ->default('active')
                            ->required(),
                    ])->columns(2),

                Section::make('Роль')
                    ->description(FilamentInlineMarkdown::toHtml(
                        'Определяет, что пользователь может делать в **этом кабинете**. Роли **консоли платформы** здесь не настраиваются.'
                    ))
                    ->schema([
                        Select::make('tenant_role')
                            ->label('Роль')
                            ->options(fn (): array => self::tenantRoleOptionsForForm())
                            ->default(fn (): string => self::defaultTenantRoleForForm())
                            ->required()
                            ->helperText('Выберите уровень доступа согласно обязанностям сотрудника. Доступные роли зависят от вашей роли в команде.'),
                    ]),
                UserPasswordFormFields::editPasswordSection(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (?string $state): string => $state ? (User::statuses()[$state] ?? $state) : ''),
                TextColumn::make('pivot_role')
                    ->label('Роль')
                    ->getStateUsing(function (User $record): string {
                        $tenant = currentTenant();
                        if (! $tenant) {
                            return '—';
                        }
                        $row = $record->tenants()->where('tenant_id', $tenant->id)->first();
                        $role = $row?->pivot->role;

                        return $role ? RoleLabels::labelForTenantMembershipRole($role) : '—';
                    }),
                TextColumn::make('last_login_at')->dateTime('d.m.Y H:i')->sortable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(User::statuses()),
            ])
            ->actions([
                EditAction::make()
                    ->visible(function (User $record): bool {
                        $user = Auth::user();

                        return $user instanceof User && $user->can('update', $record);
                    }),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function tenantRoleOptionsForForm(): array
    {
        $tenant = currentTenant();
        $actor = Auth::user();
        if (! $tenant instanceof Tenant || ! $actor instanceof User) {
            return [];
        }

        $all = RoleLabels::tenantMembershipRoleOptions();
        $livewire = Livewire::current();

        if ($livewire instanceof CreateRecord && $livewire::getResource() === self::class) {
            $role = $actor->tenants()->where('tenant_id', $tenant->id)->first()?->pivot->role;
            $keys = is_string($role) ? TenantMembershipRoleHierarchy::creatableRoleKeys($role) : [];

            return array_intersect_key($all, array_flip($keys));
        }

        if ($livewire instanceof EditRecord && $livewire::getResource() === self::class) {
            $record = $livewire->getRecord();
            if (! $record instanceof User) {
                return [];
            }
            $keys = TenantMembershipRoleHierarchy::allowedRoleKeysForAssignment(
                $actor,
                $record,
                (int) $tenant->id,
                false
            );

            return array_intersect_key($all, array_flip($keys));
        }

        return [];
    }

    public static function defaultTenantRoleForForm(): string
    {
        $opts = self::tenantRoleOptionsForForm();
        if (array_key_exists('operator', $opts)) {
            return 'operator';
        }

        $first = array_key_first($opts);

        return is_string($first) ? $first : 'operator';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
