<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Forms\Components\SeoMetaFields;
use App\Filament\Tenant\Resources\PageResource\Pages;
use App\Filament\Tenant\Resources\PageResource\RelationManagers\PageSectionsBuilderRelationManager;
use App\Filament\Tenant\Support\TenantPageRichEditor;
use App\Models\Page;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Tenant\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationLabel = 'Страницы';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $modelLabel = 'Страница';

    protected static ?string $pluralModelLabel = 'Страницы';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(['default' => 1, 'lg' => 12])
                    ->schema([
                        Section::make('Основной контент страницы')
                            ->description('Основной текст, который увидит посетитель. Для главной (slug home) страница собирается из блоков на вкладке «Блоки страницы».')
                            ->visible(fn (Get $get): bool => ($get('slug') ?: '') !== 'home')
                            ->schema([
                                TenantPageRichEditor::enhance(
                                    RichEditor::make('primary_html')
                                        ->label('Текст страницы')
                                        ->columnSpanFull()
                                        ->extraInputAttributes(['class' => 'tenant-page-primary-html-editor'])
                                )->helperText('Этот текст выводится на публичной странице в основном блоке.'),
                            ])
                            ->columnSpan(['default' => 1, 'lg' => 8]),
                        Grid::make(1)
                            ->columnSpan(['default' => 1, 'lg' => 4])
                            ->schema([
                                Section::make('Параметры страницы')
                                    ->description('Настройки URL, публикации и отображения в меню сайта.')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Название в меню и списках')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                                        TextInput::make('slug')
                                            ->label('URL-идентификатор')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true)
                                            ->live(onBlur: true)
                                            ->helperText('Путь на сайте: about-us → /about-us. Для «Контакты» / «Правила аренды» — slug contacts и usloviya-arenda. Системные пути (/booking, /admin, /faq …) зарезервированы.'),
                                        Select::make('template')
                                            ->label('Макет')
                                            ->options(['default' => 'По умолчанию'])
                                            ->default('default')
                                            ->helperText('Структура отображения, если тема поддерживает несколько макетов.'),
                                        Select::make('status')
                                            ->label('Статус публикации')
                                            ->options(Page::statuses())
                                            ->required()
                                            ->default('draft')
                                            ->helperText('Черновик и скрытые страницы не открываются публично по правилам сайта. В верхнее меню попадают только опубликованные страницы с включённым флагом меню.'),
                                        Toggle::make('show_in_main_menu')
                                            ->label('Показывать в главном меню')
                                            ->helperText('Показывать ссылку на эту страницу в верхнем меню сайта.')
                                            ->default(false),
                                        TextInput::make('main_menu_sort_order')
                                            ->label('Порядок в меню')
                                            ->numeric()
                                            ->default(0)
                                            ->required()
                                            ->helperText('Чем меньше число, тем выше пункт в меню.'),
                                    ]),
                                SeoMetaFields::make('seoMeta', true),
                            ]),
                    ]),
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
                    ->label('URL')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (Page::statuses()[$state] ?? $state) : '')
                    ->color(fn (?string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'hidden' => 'gray',
                        default => 'gray',
                    }),
                IconColumn::make('show_in_main_menu')
                    ->label('В меню')
                    ->boolean(),
                TextColumn::make('main_menu_sort_order')
                    ->label('Порядок')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sections_count')
                    ->counts('sections')
                    ->label('Блоков'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(Page::statuses()),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->emptyStateHeading('Страниц пока нет')
            ->emptyStateDescription('Создайте страницу — например «О нас», «Контакты» или лендинг.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public static function getRelations(): array
    {
        return [
            PageSectionsBuilderRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
