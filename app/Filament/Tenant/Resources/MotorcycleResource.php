<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Concerns\ResolvesDomainTermLabels;
use App\Filament\Tenant\Forms\LinkedBookableSchedulingForm;
use App\Filament\Tenant\Resources\MotorcycleResource\Form\MotorcycleFormFieldKit;
use App\Filament\Tenant\Resources\MotorcycleResource\Pages;
use App\Models\BookingSettingsPreset;
use App\Models\Motorcycle;
use App\Scheduling\BookableServiceBulkService;
use App\Scheduling\Enums\BookableServiceSettingsApplyMode;
use App\Support\FilamentMotorcycleThumbnail;
use App\Terminology\DomainTermKeys;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class MotorcycleResource extends Resource
{
    use ResolvesDomainTermLabels;

    protected static ?string $model = Motorcycle::class;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 10;

    /**
     * Тема expert_auto — лендинг инструктора: каталог «техники» (Motorcycle) не используется;
     * программы на сайте ведутся через {@see TenantServiceProgramResource}.
     */
    public static function canAccess(): bool
    {
        $tenant = currentTenant();
        if ($tenant !== null && $tenant->themeKey() === 'expert_auto') {
            return false;
        }

        return Gate::allows('manage_motorcycles');
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::RESOURCE_PLURAL, 'Мотоциклы');
    }

    public static function getModelLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::RESOURCE, 'Мотоцикл');
    }

    public static function getPluralModelLabel(): string
    {
        return static::domainTermLabel(DomainTermKeys::RESOURCE_PLURAL, 'Мотоциклы');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Карточка')
                    ->persistTabInQueryString(LinkedBookableSchedulingForm::MOTORCYCLE_TAB_QUERY_KEY)
                    ->tabs([
                        'main' => Tab::make('Основное')
                            ->id(LinkedBookableSchedulingForm::TAB_KEY_MAIN)
                            ->schema([
                                LinkedBookableSchedulingForm::motorcycleCreateNotice(),
                                Grid::make(['default' => 1, 'lg' => 12])
                                    ->schema([
                                        Section::make()
                                            ->schema([
                                                Section::make('Основная информация')
                                                    ->description('Название, идентификатор и краткое описание карточки')
                                                    ->schema(MotorcycleFormFieldKit::mainInfoFields())
                                                    ->columns(2),

                                                Section::make('Страница модели (/moto/…)')
                                                    ->description('Блоки на публичной карточке техники. Пустые поля скрываются; тезисы по сценарию при отсутствии данных можно подтянуть из категории (см. конфиг tenant_landing).')
                                                    ->schema(MotorcycleFormFieldKit::pageModelFields())
                                                    ->collapsed()
                                                    ->collapsible(),

                                                Section::make('Полное описание')
                                                    ->description('Подробное описание для карточки мотоцикла')
                                                    ->schema(MotorcycleFormFieldKit::fullDescriptionField()),

                                                Section::make('Характеристики')
                                                    ->description('Технические параметры мотоцикла')
                                                    ->schema(MotorcycleFormFieldKit::specsSections())
                                                    ->columns(1),

                                                MotorcycleFormFieldKit::seoSnippetPreviewPlaceholder(),
                                                MotorcycleFormFieldKit::seoMetaSection(),
                                            ])
                                            ->columnSpan(['default' => 12, 'lg' => 8]),

                                        Section::make('Управление карточкой')
                                            ->description('Публикация, цены и медиа')
                                            ->schema([
                                                ...MotorcycleFormFieldKit::publishingFields(),
                                                Section::make('Режим учёта и локации')
                                                    ->description('Единицы парка настраиваются после первого сохранения карточки. Локации — справочник «Инфраструктура → Локации».')
                                                    ->schema(MotorcycleFormFieldKit::fleetAndLocationCardFields())
                                                    ->columns(1)
                                                    ->compact(),
                                                ...MotorcycleFormFieldKit::mediaUploadFields(),
                                            ])
                                            ->columns(1)
                                            ->columnSpan(['default' => 12, 'lg' => 4]),
                                    ]),
                            ]),
                        LinkedBookableSchedulingForm::TAB_KEY_ONLINE_BOOKING => LinkedBookableSchedulingForm::motorcycleOnlineBookingTab(),
                    ])
                    ->columnSpan(['default' => 12, 'lg' => 12]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $isGrid = request()->cookie('moto_catalog_grid', 'false') === 'true' || session('moto_catalog_grid', false);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('media'))
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100, 200])
            ->contentGrid($isGrid ? [
                'md' => 2,
                'xl' => 3,
            ] : null)
            ->columns([
                ImageColumn::make('cover_thumb')
                    ->label('Фото')
                    ->getStateUsing(fn (Motorcycle $record): ?string => $record->cover_url)
                    ->defaultImageUrl(fn (): string => FilamentMotorcycleThumbnail::placeholderDataUrl())
                    ->checkFileExistence(false)
                    ->imageSize(48)
                    ->square()
                    ->extraImgAttributes([
                        'class' => 'rounded-lg object-cover',
                        'loading' => 'lazy',
                        'decoding' => 'async',
                    ])
                    ->extraCellAttributes(['class' => 'fi-motorcycle-cover-cell'])
                    ->tooltip('Обложка карточки; наведите для увеличения'),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->label('Бренд')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Категория')
                    ->sortable(),
                TextColumn::make('price_per_day')
                    ->label('Цена / сутки')
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Motorcycle::statuses()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'booked', 'maintenance' => 'warning',
                        'hidden', 'archived' => 'gray',
                        default => 'gray',
                    }),
                IconColumn::make('show_on_home')
                    ->label('На главной')
                    ->boolean(),
                IconColumn::make('show_in_catalog')
                    ->label('В каталоге')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Motorcycle::statuses()),
                TrashedFilter::make(),
            ])
            ->headerActions([
                Action::make('toggle_view')
                    ->label($isGrid ? 'Списком' : 'Сеткой')
                    ->icon($isGrid ? 'heroicon-o-list-bullet' : 'heroicon-o-squares-2x2')
                    ->action(function () use ($isGrid) {
                        session(['moto_catalog_grid' => ! $isGrid]);
                        Cookie::queue('moto_catalog_grid', ! $isGrid ? 'true' : 'false', 60 * 24 * 365);

                        return redirect(request()->header('Referer'));
                    }),
            ])
            ->actions([
                Action::make('quick_edit')
                    ->label('Быстрая правка')
                    ->icon('heroicon-o-bolt')
                    ->slideOver()
                    ->fillForm(fn (Motorcycle $record): array => [
                        'status' => $record->status,
                        'price_per_day' => $record->price_per_day,
                        'sort_order' => $record->sort_order,
                    ])
                    ->form([
                        Select::make('status')
                            ->label('Статус')
                            ->options(Motorcycle::statuses())
                            ->required(),
                        TextInput::make('price_per_day')
                            ->label('Цена за сутки')
                            ->numeric()
                            ->suffix('₽')
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Сортировка')
                            ->numeric(),
                    ])
                    ->action(function (Motorcycle $record, array $data): void {
                        $record->update($data);
                        Notification::make()->title('Обновлено')->success()->send();
                    })
                    ->color('gray'),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('enable_online_booking_by_preset')
                        ->label('Включить онлайн-запись по группе')
                        ->icon('heroicon-o-calendar-days')
                        ->visible(fn (): bool => LinkedBookableSchedulingForm::schedulingFormEditable())
                        ->form([
                            Select::make('preset_id')
                                ->label('Группа настроек')
                                ->options(fn (): array => BookingSettingsPreset::query()
                                    ->where('tenant_id', currentTenant()?->id)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->required(),
                            Toggle::make('sync_title_from_source')
                                ->label('Синхронизировать название услуги с моделью')
                                ->default(true),
                        ])
                        ->modalDescription('Действие активирует связанную услугу и цель расписания. При необходимости будет создана услуга записи для карточки. Чтобы клиенты видели слоты, у целей должны быть настроены ресурсы расписания и правила доступности.')
                        ->action(function (BulkAction $action, array $data): void {
                            $preset = BookingSettingsPreset::query()->find((int) ($data['preset_id'] ?? 0));
                            if ($preset === null) {
                                Notification::make()->title('Группа не найдена')->danger()->send();

                                return;
                            }
                            $syncTitle = (bool) ($data['sync_title_from_source'] ?? true);
                            $bulk = app(BookableServiceBulkService::class);
                            foreach ($action->getSelectedRecords() as $record) {
                                if (! $record instanceof Motorcycle) {
                                    continue;
                                }
                                try {
                                    $bulk->applyPresetToMotorcycle(
                                        $record,
                                        $preset,
                                        true,
                                        BookableServiceSettingsApplyMode::Replace,
                                        $syncTitle,
                                    );
                                } catch (\InvalidArgumentException) {
                                    Notification::make()->title('Ошибка: проверьте принадлежность карточек клиенту.')->danger()->send();

                                    return;
                                }
                            }
                            Notification::make()->title('Онлайн-запись включена по выбранным карточкам')->success()->send();
                        }),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->emptyStateHeading('В каталоге пока пусто')
            ->emptyStateDescription('Добавьте карточку техники — её увидят посетители, если включён показ в каталоге.')
            ->emptyStateIcon('heroicon-o-truck');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMotorcycles::route('/'),
            'create' => Pages\CreateMotorcycle::route('/create'),
            'edit' => Pages\EditMotorcycle::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
