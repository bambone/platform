<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Forms\Components\TenantSpatieMediaLibraryFileUpload;
use App\Filament\Tenant\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Tenant\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationLabel = 'Отзывы';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 20;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $modelLabel = 'Отзыв';

    protected static ?string $pluralModelLabel = 'Отзывы';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $isExpertAuto = static fn (): bool => currentTenant()?->themeKey() === 'expert_auto';

        return $schema
            ->components([
                Section::make('Основное')
                    ->description('Поля попадают в блок отзывов на сайте (секция «Отзывы» в конструкторе страниц). Публикуйте только опубликованные записи.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Имя на сайте')
                            ->required()
                            ->maxLength(255)
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('Как будет подписан автор на карточке отзыва (имя или имя + контекст).'),
                        TextInput::make('city')
                            ->label('Город')
                            ->maxLength(255)
                            ->placeholder('Например, Челябинск')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('Необязательно. Показывается рядом с именем, если тема выводит город.'),
                        TextInput::make('headline')
                            ->label('Заголовок / лид')
                            ->maxLength(255)
                            ->placeholder('Короткая тема отзыва')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('Одна строка над текстом: тема или эмоция («Контраварийка и зима»). На сайте может идти бейджем или подзаголовком.'),
                        TextInput::make('category_key')
                            ->label('Ключ темы (программа)')
                            ->maxLength(64)
                            ->placeholder('counter-emergency')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip(
                                'Связь с программой или темой для фильтра/бейджа на сайте. Обычно slug из «Каталог → Программы» '
                                .'(например single-session, city-driving, counter-emergency). Допустимы и короткие ключи для бейджа: '
                                .'parking, city, winter-driving, confidence, motorsport. Пусто — отзыв без привязки к теме.'
                            ),
                        Textarea::make('text_short')
                            ->label('Краткий текст')
                            ->rows(2)
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('Анонс или первые предложения: для карточек и списков. Если пусто, при сохранении может быть сгенерирован из полного текста.'),
                        Textarea::make('text_long')
                            ->label('Полный текст отзыва')
                            ->rows(5)
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('Основной текст на странице. HTML не обязателен — достаточно обычного текста; переносы строк сохраняются.'),
                        Textarea::make('text')
                            ->label('Текст (единое поле, legacy)')
                            ->rows(3)
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip(
                                'Для совместимости со старыми данными. Если оставить пустым, при сохранении подставится полный текст '
                                .'или краткий. Редактировать удобнее «Полный текст» — это основной источник.'
                            )
                            ->helperText('Обычно не заполняют вручную: заполнится из «Полный» / «Краткий», если пусто.'),
                        TextInput::make('rating')
                            ->label('Оценка')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5)
                            ->default(5)
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('Число от 1 до 5. На сайте может отображаться звёздами, если блок это поддерживает.'),
                        Select::make('media_type')
                            ->label('Тип контента')
                            ->options(['text' => 'Только текст', 'video' => 'С видео'])
                            ->default('text')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('«С видео» — укажите ниже ссылку; для встроенного плеера подойдёт прямая ссылка на .mp4 / .webm или страница с плеером.'),
                        TextInput::make('video_url')
                            ->label('Ссылка на видео')
                            ->url()
                            ->maxLength(2048)
                            ->visible(fn (Get $get): bool => ($get('media_type') ?? 'text') === 'video')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('Обязательно, если выбран тип «С видео». Иначе поле можно не трогать.'),
                        Select::make('motorcycle_id')
                            ->label('Карточка в каталоге техники')
                            ->relationship('motorcycle', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn () => ! $isExpertAuto())
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('Для аренды мотопарка: привязка отзыва к модели из каталога. Для сайта инструктора (expert) поле скрыто — не используется.'),
                        DatePicker::make('date')
                            ->label('Дата отзыва')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('Дата для сортировки и отображения («когда оставлен отзыв»). Можно поставить дату публикации.'),
                        TextInput::make('source')
                            ->label('Источник (служебно)')
                            ->maxLength(255)
                            ->placeholder('site, yandex, …')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('Метка для себя: откуда пришёл отзыв. На публичный сайт обычно не выводится.'),
                    ])->columns(2),

                Section::make('Медиа и статус')
                    ->schema([
                        TenantSpatieMediaLibraryFileUpload::make('avatar')
                            ->collection('avatar')
                            ->disk(config('media-library.disk_name'))
                            ->visibility('public')
                            ->conversionsDisk(config('media-library.disk_name'))
                            ->image()
                            ->label('Фото (аватар)')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('Квадратное или портретное фото лица; лучше не меньше 400×400 px. Показывается в карточке отзыва.'),
                        TextInput::make('sort_order')
                            ->label('Порядок в списке')
                            ->numeric()
                            ->default(0)
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('Меньшее число — выше в списке внутри своей группы (избранные и обычные сортируются отдельно на сайте).'),
                        Select::make('status')
                            ->label('Статус публикации')
                            ->options(Review::statuses())
                            ->required()
                            ->default('published')
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('На сайте попадают только отзывы в статусе «Опубликован». Черновик и «Скрыт» — только в админке.'),
                        Toggle::make('is_featured')
                            ->label('Крупная карточка (спотлайт)')
                            ->default(false)
                            ->hintIcon('heroicon-o-information-circle')
                            ->hintIconTooltip('Включите для 1–3 главных отзывов: крупный блок и бейдж на лендинге. Остальные — без этой отметки.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('city')->placeholder('—'),
                TextColumn::make('text')->limit(40)->placeholder('—'),
                TextColumn::make('rating'),
                TextColumn::make('motorcycle.name')->placeholder('—'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (?string $state): string => $state ? (Review::statuses()[$state] ?? $state) : ''),
                IconColumn::make('is_featured')->boolean(),
                TextColumn::make('created_at')->date('d.m.Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Review::statuses()),
            ])
            ->defaultSort('sort_order')
            ->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'create' => Pages\CreateReview::route('/create'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
