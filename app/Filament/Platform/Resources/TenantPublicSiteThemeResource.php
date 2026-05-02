<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\TenantPublicSiteThemeResource\Pages;
use App\Filament\Shared\Lifecycle\AdminFilamentDelete;
use App\Filament\Support\AdminEmptyState;
use App\Models\TenantPublicSiteTheme;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class TenantPublicSiteThemeResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = TenantPublicSiteTheme::class;

    protected static ?string $navigationLabel = 'Темы публичных сайтов';

    protected static ?string $modelLabel = 'Тема публичного сайта';

    protected static ?string $pluralModelLabel = 'Темы публичных сайтов';

    protected static ?string $panel = 'platform';

    protected static string|\UnitEnum|null $navigationGroup = 'Платформа';

    protected static ?int $navigationSort = 19;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('sort_order')->orderBy('name');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Каталог тем')
                    ->description('Ключ должен совпадать с каталогом Blade `tenant/themes/{theme_key}` и колонкой `tenants.theme_key`. После появления клиентов ключ не переименовывают — только подпись и активность.')
                    ->schema([
                        TextInput::make('theme_key')
                            ->label('Ключ темы')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit')
                            ->helperText('Латиница, цифры, одиночные `-`/`_`; не использовать `auto`.')
                            ->rules([
                                'regex:/^[a-z0-9][a-z0-9_-]{0,62}$/',
                                Rule::notIn(['auto']),
                            ]),
                        TextInput::make('name')
                            ->label('Название в админках')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Описание (внутреннее)')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('sort_order')
                            ->label('Порядок в списках')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Активна (доступна для новых назначений)')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminEmptyState::applyInitial(
            $table
                ->columns([
                    TextColumn::make('theme_key')
                        ->label('Ключ')
                        ->searchable(),
                    TextColumn::make('name')
                        ->label('Название')
                        ->searchable(),
                    IconColumn::make('is_active')
                        ->label('В каталоге')
                        ->boolean(),
                    TextColumn::make('sort_order')
                        ->label('Порядок')
                        ->sortable(),
                    TextColumn::make('tenants_count')
                        ->label('Клиентов')
                        ->counts('tenantsByThemeKey'),
                ])
                ->recordActions([
                    EditAction::make(),
                    AdminFilamentDelete::configureTableDeleteAction(DeleteAction::make()),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        AdminFilamentDelete::makeBulkDeleteAction()
                            ->modalHeading('Удалить темы каталога?')
                            ->modalDescription('Удалятся только темы без привязанных клиентов; иначе операция будет отменена.'),
                    ]),
                ])
                ->defaultSort('sort_order'),
            'Каталог тем пуст',
            'Добавьте ключи тем, которые можно назначать клиенту в карточке клиента (поле theme_key публичного сайта).',
            'heroicon-o-paint-brush',
            [CreateAction::make()->label('Добавить тему')],
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantPublicSiteThemes::route('/'),
            'create' => Pages\CreateTenantPublicSiteTheme::route('/create'),
            'edit' => Pages\EditTenantPublicSiteTheme::route('/{record}/edit'),
        ];
    }
}
