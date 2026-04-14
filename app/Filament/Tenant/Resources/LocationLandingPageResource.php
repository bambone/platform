<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Forms\Components\SeoMetaFields;
use App\Filament\Support\FilamentInlineMarkdown;
use App\Filament\Tenant\Resources\LocationLandingPageResource\Pages;
use App\Models\LocationLandingPage;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Tenant\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LocationLandingPageResource extends Resource
{
    protected static ?string $model = LocationLandingPage::class;

    protected static ?string $navigationLabel = 'Локации (SEO)';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 42;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $modelLabel = 'Локация';

    protected static ?string $pluralModelLabel = 'Локации (посадочные)';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Локационная посадочная')
                    ->description(FilamentInlineMarkdown::toHtml(
                        '**Зачем:** отдельная страница под **город, район или точку выдачи** (`/locations/{slug}`), чтобы люди находили вас по запросам вроде «аренда мото в Анапе». **Что писать:** короткий **заголовок (H1)**, **интро** с оффером и доверием, **основной текст** — как добраться, зона работы, нюансы выдачи, внутренние ссылки на каталог и контакты. **Slug** — латиница в URL (например `anapa`). Вкладки **SEO** — title/description для выдачи; публикация — только когда текст готов.'
                    ))
                    ->schema([
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(191)
                            ->unique(ignoreRecord: true)
                            ->helperText('Сегмент URL: /locations/{slug}. Латиница, без пробелов.'),
                        TextInput::make('title')->required()->maxLength(255)->helperText('Внутреннее имя в админке; на сайте можно не показывать.'),
                        TextInput::make('h1')->maxLength(255)->helperText('Главный заголовок на публичной странице.'),
                        Textarea::make('intro')->rows(3)->columnSpanFull()->helperText('1–3 предложения над основным текстом.'),
                        Textarea::make('body')->rows(8)->columnSpanFull()->helperText('Полезный текст для человека и поиска: география, условия, ссылки на мотоциклы и разделы сайта.'),
                        TextInput::make('sort_order')->numeric()->default(0),
                        Toggle::make('is_published')->label('Опубликовано')->default(false),
                        SeoMetaFields::make(useTabs: true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')->searchable(),
                TextColumn::make('title')->searchable()->limit(40),
                IconColumn::make('is_published')->boolean(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocationLandingPages::route('/'),
            'create' => Pages\CreateLocationLandingPage::route('/create'),
            'edit' => Pages\EditLocationLandingPage::route('/{record}/edit'),
        ];
    }
}
