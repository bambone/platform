<?php

namespace App\Filament\Platform\Resources;

use App\Auth\AccessRoles;
use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\PlatformUserResource\Pages;
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
        $labels = [
            'platform_owner' => 'platform_owner',
            'platform_admin' => 'platform_admin',
            'support_manager' => 'support_manager',
        ];

        return $schema
            ->components([
                Section::make('Профиль')
                    ->description('Только роли Platform Console. Tenant membership настраивается отдельно (в клиенте).')
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
                        CheckboxList::make('platform_roles')
                            ->label('Роли платформы')
                            ->options($labels)
                            ->required()
                            ->columns(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('platform_roles_list')
                    ->label('Роли платформы')
                    ->getStateUsing(fn (User $record): string => $record->roles
                        ->whereIn('name', AccessRoles::platformRoles())
                        ->pluck('name')
                        ->join(', ')),
            ])
            ->actions([EditAction::make()]);
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
