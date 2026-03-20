<?php

namespace App\Filament\Tenant\Resources;

use App\Auth\AccessRoles;
use App\Filament\Tenant\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Команда';

    protected static ?string $modelLabel = 'Пользователь';

    protected static ?string $pluralModelLabel = 'Команда';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        $tenant = currentTenant();
        $query = parent::getEloquentQuery();

        if ($tenant) {
            $query->whereHas('tenants', fn (Builder $q) => $q->where('tenants.id', $tenant->id));
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        $roleOptions = array_combine(
            AccessRoles::tenantMembershipRolesForPanel(),
            AccessRoles::tenantMembershipRolesForPanel()
        );

        return $schema
            ->components([
                Section::make('Профиль')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255),
                        Select::make('status')
                            ->options(User::statuses())
                            ->default('active')
                            ->required(),
                    ])->columns(2),

                Section::make('Роль в этом клиенте')
                    ->description('Доступ в Tenant Admin определяется membership и этой ролью (не путать с Platform Console).')
                    ->schema([
                        Select::make('tenant_role')
                            ->label('Роль')
                            ->options($roleOptions)
                            ->default('operator')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (?string $state): string => $state ? (User::statuses()[$state] ?? $state) : ''),
                TextColumn::make('pivot_role')
                    ->label('Роль в клиенте')
                    ->getStateUsing(function (User $record): string {
                        $tenant = currentTenant();
                        if (! $tenant) {
                            return '—';
                        }
                        $row = $record->tenants()->where('tenant_id', $tenant->id)->first();

                        return $row?->pivot->role ?? '—';
                    }),
                TextColumn::make('last_login_at')->dateTime('d.m.Y H:i')->sortable()->placeholder('—'),
                TextColumn::make('created_at')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(User::statuses()),
            ])
            ->actions([
                EditAction::make(),
            ]);
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
