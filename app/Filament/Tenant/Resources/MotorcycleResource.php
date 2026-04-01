<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Forms\Components\SeoMetaFields;
use App\Filament\Tenant\Concerns\ResolvesDomainTermLabels;
use App\Filament\Tenant\Resources\MotorcycleResource\Pages;
use App\Models\Motorcycle;
use App\Support\CatalogHighlightNormalizer;
use App\Support\FilamentMotorcycleThumbnail;
use App\Terminology\DomainTermKeys;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use App\Filament\Forms\Components\TenantSpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;
use UnitEnum;

class MotorcycleResource extends Resource
{
    use ResolvesDomainTermLabels;

    protected static ?string $model = Motorcycle::class;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 10;

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
            ->columns(['default' => 1, 'lg' => 12])
            ->components([
                // Левая колонка 8/12 — основной контент
                Section::make()
                    ->schema([
                        Section::make('Основная информация')
                            ->description('Название, идентификатор и краткое описание карточки')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Название')
                                    ->id('motorcycle-name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                                        if ($operation === 'create' && $state) {
                                            $set('slug', Str::slug($state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->label('URL-идентификатор')
                                    ->id('motorcycle-slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Адрес карточки в каталоге, например /catalog/your-slug. Латиница, цифры и дефис.'),
                                TextInput::make('brand')
                                    ->label('Бренд')
                                    ->id('motorcycle-brand')
                                    ->maxLength(255),
                                TextInput::make('model')
                                    ->label('Модель')
                                    ->id('motorcycle-model')
                                    ->maxLength(255),
                                Textarea::make('short_description')
                                    ->label('Позиционирование в каталоге')
                                    ->id('motorcycle-short-description')
                                    ->rows(3)
                                    ->helperText('1–2 короткие строки: для какого сценария модель и чем отличается от соседних. Только правдивый маркетинговый смысл, без выдуманных цифр.')
                                    ->columnSpanFull(),
                                TextInput::make('catalog_scenario')
                                    ->label('Сценарий / кому подойдёт')
                                    ->id('motorcycle-catalog-scenario')
                                    ->maxLength(120)
                                    ->placeholder('Например: Туристу и трассе')
                                    ->columnSpanFull(),
                                Fieldset::make('Быстрые преимущества (чипы в каталоге, словарь на сайте)')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                Select::make('catalog_highlight_1')
                                                    ->label('Чип 1')
                                                    ->id('motorcycle-catalog-highlight-1')
                                                    ->placeholder('—')
                                                    ->formatStateUsing(fn (?string $state): ?string => CatalogHighlightNormalizer::normalizeToKey($state))
                                                    ->options(fn (): array => CatalogHighlightNormalizer::selectOptions()),
                                                Select::make('catalog_highlight_2')
                                                    ->label('Чип 2')
                                                    ->id('motorcycle-catalog-highlight-2')
                                                    ->placeholder('—')
                                                    ->formatStateUsing(fn (?string $state): ?string => CatalogHighlightNormalizer::normalizeToKey($state))
                                                    ->options(fn (): array => CatalogHighlightNormalizer::selectOptions()),
                                                Select::make('catalog_highlight_3')
                                                    ->label('Чип 3')
                                                    ->id('motorcycle-catalog-highlight-3')
                                                    ->placeholder('—')
                                                    ->formatStateUsing(fn (?string $state): ?string => CatalogHighlightNormalizer::normalizeToKey($state))
                                                    ->options(fn (): array => CatalogHighlightNormalizer::selectOptions()),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                                TextInput::make('catalog_price_note')
                                    ->label('Подпись под ценой в каталоге')
                                    ->id('motorcycle-catalog-price-note')
                                    ->maxLength(80)
                                    ->placeholder('Только реальное условие')
                                    ->helperText('Необязательно. Например: «за сутки», «бронь по предоплате» — только если это действительно так.')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Страница модели (/moto/…)')
                            ->description('Блоки на публичной карточке техники. Пустые поля скрываются; тезисы по сценарию при отсутствии данных можно подтянуть из категории (см. конфиг tenant_landing).')
                            ->schema([
                                Textarea::make('detail_audience')
                                    ->label('Кому подойдёт')
                                    ->id('motorcycle-detail-audience')
                                    ->rows(3)
                                    ->helperText('1–3 предложения. Если пусто — на сайте используется сценарий из поля выше.')
                                    ->columnSpanFull(),
                                Textarea::make('detail_use_case_bullets')
                                    ->label('Сценарий: тезисы (по одному на строку, до 4)')
                                    ->id('motorcycle-detail-use-case')
                                    ->rows(5)
                                    ->formatStateUsing(function ($state): string {
                                        if (is_array($state)) {
                                            return implode("\n", array_filter($state, 'filled'));
                                        }

                                        return '';
                                    })
                                    ->dehydrateStateUsing(function (?string $state): array {
                                        if ($state === null || trim($state) === '') {
                                            return [];
                                        }
                                        $lines = preg_split('/\r\n|\r|\n/', $state) ?: [];
                                        $lines = array_values(array_filter(array_map('trim', $lines), fn (string $l): bool => $l !== ''));

                                        return array_slice($lines, 0, 4);
                                    })
                                    ->columnSpanFull(),
                                Textarea::make('detail_advantage_bullets')
                                    ->label('Ключевые плюсы (по одному на строку, до 6)')
                                    ->id('motorcycle-detail-advantages')
                                    ->rows(6)
                                    ->formatStateUsing(function ($state): string {
                                        if (is_array($state)) {
                                            return implode("\n", array_filter($state, 'filled'));
                                        }

                                        return '';
                                    })
                                    ->dehydrateStateUsing(function (?string $state): array {
                                        if ($state === null || trim($state) === '') {
                                            return [];
                                        }
                                        $lines = preg_split('/\r\n|\r|\n/', $state) ?: [];
                                        $lines = array_values(array_filter(array_map('trim', $lines), fn (string $l): bool => $l !== ''));

                                        return array_slice($lines, 0, 6);
                                    })
                                    ->columnSpanFull(),
                                Textarea::make('detail_rental_notes')
                                    ->label('Аренда: примечания к этой модели')
                                    ->id('motorcycle-detail-rental')
                                    ->rows(4)
                                    ->helperText('Только проверяемые формулировки. Общие условия — на странице «Правила аренды».')
                                    ->columnSpanFull(),
                            ])
                            ->collapsed()
                            ->collapsible(),

                        Section::make('Полное описание')
                            ->description('Подробное описание для карточки мотоцикла')
                            ->schema([
                                RichEditor::make('full_description')
                                    ->label('Описание')
                                    ->id('motorcycle-full-description')
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Характеристики')
                            ->description('Технические параметры мотоцикла')
                            ->schema([
                                Section::make('Базовые параметры')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('engine_cc')
                                                    ->label('Объём двигателя')
                                                    ->id('motorcycle-engine-cc')
                                                    ->numeric()
                                                    ->suffix('см³'),
                                                TextInput::make('power')
                                                    ->label('Мощность')
                                                    ->id('motorcycle-power')
                                                    ->numeric()
                                                    ->suffix('л.с.'),
                                                TextInput::make('transmission')
                                                    ->label('Трансмиссия')
                                                    ->id('motorcycle-transmission')
                                                    ->maxLength(255),
                                                TextInput::make('year')
                                                    ->label('Год выпуска')
                                                    ->id('motorcycle-year')
                                                    ->numeric(),
                                                TextInput::make('mileage')
                                                    ->label('Пробег')
                                                    ->id('motorcycle-mileage')
                                                    ->numeric()
                                                    ->suffix('км'),
                                            ]),
                                    ])
                                    ->columns(1)
                                    ->compact()
                                    ->secondary(),
                                Section::make('Дополнительные характеристики (расширенный режим)')
                                    ->description('Пары «название — значение» для редких полей. Основные поля выше предпочтительнее. Не используйте без необходимости — опечатки в ключах не попадут на сайт автоматически.')
                                    ->schema([
                                        KeyValue::make('specs_json')
                                            ->label('Произвольные параметры')
                                            ->id('motorcycle-specs-json')
                                            ->keyLabel('Название')
                                            ->valueLabel('Значение')
                                            ->reorderable(),
                                    ])
                                    ->columns(1)
                                    ->compact()
                                    ->secondary()
                                    ->collapsed()
                                    ->collapsible(),
                            ])
                            ->columns(1),

                        SeoMetaFields::make(useTabs: true),
                    ])
                    ->columnSpan(['default' => 12, 'lg' => 8]),

                // Правая колонка 4/12 — единый sidebar модуль
                Section::make('Управление карточкой')
                    ->description('Публикация, цены и медиа')
                    ->schema([
                        Select::make('status')
                            ->label('Статус')
                            ->id('motorcycle-status')
                            ->options(Motorcycle::statuses())
                            ->required()
                            ->default('available'),
                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->id('motorcycle-sort-order')
                            ->numeric()
                            ->default(0),
                        Toggle::make('show_on_home')
                            ->label('Показывать на главной')
                            ->id('motorcycle-show-on-home')
                            ->default(false),
                        Toggle::make('show_in_catalog')
                            ->label('Показывать в каталоге')
                            ->id('motorcycle-show-in-catalog')
                            ->default(true),
                        Toggle::make('is_recommended')
                            ->label('Рекомендуемый')
                            ->id('motorcycle-is-recommended')
                            ->default(false),
                        Select::make('category_id')
                            ->label('Категория')
                            ->id('motorcycle-category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('price_per_day')
                            ->label('Цена за день')
                            ->id('motorcycle-price-per-day')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->suffix('₽'),
                        TextInput::make('price_2_3_days')
                            ->label('2–3 дня')
                            ->id('motorcycle-price-2-3-days')
                            ->numeric()
                            ->suffix('₽'),
                        TextInput::make('price_week')
                            ->label('Неделя')
                            ->id('motorcycle-price-week')
                            ->numeric()
                            ->suffix('₽'),
                        TenantSpatieMediaLibraryFileUpload::make('cover')
                            ->collection('cover')
                            ->disk(config('media-library.disk_name'))
                            ->visibility('public')
                            ->conversionsDisk(config('media-library.disk_name'))
                            ->image()
                            ->label('Обложка')
                            ->helperText('Основное изображение карточки. Рекомендуется 16:9. При редактировании файл сохраняется в медиатеку сразу после успешной загрузки. При создании новой карточки — после первого сохранения формы.')
                            ->id('motorcycle-cover')
                            ->columnSpanFull()
                            ->fetchFileInformation(false)
                            ->orientImagesFromExif(false)
                            ->maxSize(15360)
                            ->afterStateUpdated(self::persistMotorcycleMediaAfterUpload(...)),
                        TenantSpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->disk(config('media-library.disk_name'))
                            ->visibility('public')
                            ->conversionsDisk(config('media-library.disk_name'))
                            ->image()
                            ->multiple()
                            ->maxFiles(10)
                            ->reorderable()
                            ->label('Галерея')
                            ->helperText('Дополнительные изображения для слайдера. На экране редактирования новые файлы сохраняются сразу после загрузки.')
                            ->id('motorcycle-gallery')
                            ->columnSpanFull()
                            ->fetchFileInformation(false)
                            ->orientImagesFromExif(false)
                            ->maxSize(15360)
                            ->afterStateUpdated(self::persistMotorcycleMediaAfterUpload(...)),
                    ])
                    ->columns(1)
                    ->columnSpan(['default' => 12, 'lg' => 4]),
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
                    ->extraImgAttributes(['class' => 'rounded-lg object-cover'])
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

    /**
     * После изменения состояния FileUpload: сразу синхронизировать медиатеку (как при «Сохранить»),
     * если карточка уже существует в БД. На создании записи record ещё нет — файлы сохранятся при первом submit.
     */
    protected static function persistMotorcycleMediaAfterUpload(TenantSpatieMediaLibraryFileUpload $component): void
    {
        $record = $component->getRecord();
        if (! $record instanceof Model || ! $record->exists) {
            return;
        }

        $rawState = $component->getRawState() ?? [];
        $hadTemporaryUpload = collect($rawState)->contains(
            fn (mixed $file): bool => $file instanceof TemporaryUploadedFile
        );

        try {
            $component->deleteAbandonedFiles();
            $component->saveUploadedFiles();
        } catch (Throwable $e) {
            report($e);
            Notification::make()
                ->title('Не удалось сохранить файл в хранилище')
                ->body(config('app.debug') ? $e->getMessage() : 'Проверьте MEDIA_DISK, очередь конверсий и логи сервера.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($hadTemporaryUpload) {
            Notification::make()
                ->title('Изображение сохранено')
                ->success()
                ->duration(3500)
                ->send();
        }
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
