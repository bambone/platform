<?php

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\Concerns\GrantsPlatformPanelAccess;
use App\Filament\Platform\Resources\TenantResource\Pages;
use App\Filament\Platform\Resources\TenantResource\RelationManagers\TenantUsersRelationManager;
use App\Models\TemplatePreset;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantResource extends Resource
{
    use GrantsPlatformPanelAccess;

    protected static ?string $model = Tenant::class;

    protected static ?string $modelLabel = 'Клиент';

    protected static ?string $pluralModelLabel = 'Клиенты';

    protected static ?string $navigationLabel = 'Клиенты';

    protected static ?string $panel = 'platform';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->description('Клиент платформы — отдельный сайт и данные. Название и URL-идентификатор часто видны в адресах и внутренних списках.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Например: ООО «Прокат»'),
                        TextInput::make('legal_name')
                            ->label('Юридическое название')
                            ->maxLength(255)
                            ->helperText('Для договоров и счетов, если отличается от краткого названия.'),
                        TextInput::make('slug')
                            ->label('URL-идентификатор')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Используется в техническом поддомене и ссылках. Латиница, цифры и дефис.'),
                        Select::make('theme_key')
                            ->label('Тема публичного сайта')
                            ->options([
                                'default' => 'По умолчанию',
                                'moto' => 'Мото',
                                'auto' => 'Авто',
                            ])
                            ->default('default')
                            ->required()
                            ->helperText('Пресет внешнего вида (Blade в tenant/themes/{ключ}). Недостающие страницы берутся из слоя default и движка.'),
                        TextInput::make('brand_name')
                            ->label('Бренд на сайте')
                            ->maxLength(255)
                            ->helperText('Как называть клиента для посетителей; можно совпадать с названием.'),
                        Select::make('status')
                            ->label('Статус клиента')
                            ->options(Tenant::statuses())
                            ->default('trial')
                            ->required()
                            ->helperText('Пробный — ограниченный период. Активен — полноценная работа. Приостановлен — доступ ограничен.'),
                        Select::make('plan_id')
                            ->label('Тариф')
                            ->relationship('plan', 'name')
                            ->preload()
                            ->helperText('Определяет лимиты и доступные функции.'),
                        Select::make('template_preset_id')
                            ->label('Шаблон сайта при создании')
                            ->options(TemplatePreset::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visibleOn('create')
                            ->helperText('После сохранения шаблон копируется в сайт клиента. При редактировании клиента поле скрыто — шаблон уже применён.'),
                    ])->columns(2),

                Section::make('Ответственные')
                    ->description('Не обязательно сразу. Владелец и менеджер поддержки помогают ориентироваться в карточке клиента.')
                    ->schema([
                        Select::make('owner_user_id')
                            ->label('Владелец (контакт)')
                            ->relationship('owner', 'name')
                            ->preload(),
                        Select::make('support_manager_id')
                            ->label('Менеджер поддержки')
                            ->relationship('supportManager', 'name')
                            ->preload(),
                    ])->columns(2),

                Section::make('Регион и валюта')
                    ->description('Влияет на время, формат и отображение цен в кабинете и на сайте.')
                    ->schema([
                        TextInput::make('timezone')
                            ->label('Часовой пояс')
                            ->default('Europe/Moscow')
                            ->maxLength(50)
                            ->helperText('Например: Europe/Moscow — для календаря и бронирований.'),
                        TextInput::make('locale')
                            ->label('Локаль')
                            ->default('ru')
                            ->maxLength(10)
                            ->helperText('Язык интерфейса кабинета и сайта, если поддерживается темой.'),
                        TextInput::make('country')
                            ->label('Страна (код)')
                            ->maxLength(2)
                            ->placeholder('RU')
                            ->helperText('Двухбуквенный код ISO, если нужен для настроек.'),
                        TextInput::make('currency')
                            ->label('Валюта')
                            ->default('RUB')
                            ->maxLength(3)
                            ->placeholder('RUB')
                            ->helperText('Трёхбуквенный код для цен и отчётов.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Идентификатор')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (Tenant::statuses()[$state] ?? $state) : '—')
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'trial' => 'warning',
                        'suspended' => 'danger',
                        'archived' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('plan.name')
                    ->label('Тариф')
                    ->placeholder('—'),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Удалить выбранных клиентов?')
                        ->modalDescription('Действие необратимо: клиенты и связанные данные могут быть удалены из базы. Сайты перестанут открываться. Продолжайте только если это осознанное решение.'),
                ]),
            ])
            ->emptyStateHeading('Клиентов пока нет')
            ->emptyStateDescription('Создайте первого клиента мастером «Новый клиент» или кнопкой «Создать».')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }

    public static function getRelations(): array
    {
        return [
            TenantUsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
