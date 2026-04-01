<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Forms\Components\SeoMetaFields;
use App\Filament\Tenant\Resources\PageResource\Pages;
use App\Filament\Tenant\Resources\PageResource\RelationManagers\SectionsRelationManager;
use App\Models\Page;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
            ->components([
                Section::make('Страница на сайте')
                    ->description('Контентные страницы сайта для посетителей. Черновик не виден публично; опубликованная — доступна по URL.')
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
                            ->helperText('Часть адреса страницы, например: about-us → /about-us'),
                        Select::make('template')
                            ->label('Макет')
                            ->options(['default' => 'По умолчанию'])
                            ->default('default')
                            ->helperText('Определяет структуру отображения, если тема поддерживает несколько макетов.'),
                        Select::make('status')
                            ->label('Статус публикации')
                            ->options(Page::statuses())
                            ->required()
                            ->default('draft')
                            ->helperText('Черновик — только в кабинете. Опубликован — на сайте. Скрыт — не в меню, но может открываться по прямой ссылке (зависит от темы).'),
                    ])->columns(2),
                SeoMetaFields::make(),
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
            SectionsRelationManager::class,
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
