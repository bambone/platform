<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Forms\Components\SeoMetaFields;
use App\Filament\Support\FilamentInlineMarkdown;
use App\Filament\Tenant\Resources\SeoLandingPageResource\Pages;
use App\Models\SeoLandingPage;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
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

class SeoLandingPageResource extends Resource
{
    protected static ?string $model = SeoLandingPage::class;

    protected static ?string $navigationLabel = 'SEO-посадочные';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 43;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $modelLabel = 'Посадочная';

    protected static ?string $pluralModelLabel = 'SEO-посадочные';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Тематическая SEO-посадочная')
                    ->description(FilamentInlineMarkdown::toHtml(
                        '**Зачем:** страница под **узкий поисковый интент** (тип мото, условия, услуга) по адресу `/landings/{slug}` — рядом с каталогом, но с **своим текстом и метаданными**. **Что писать:** чёткий **H1** под запрос, **интро** с ответом, **body** с фактами и ссылками на релевантные мото/страницы. **Критерии** — опциональные пары ключ/значение для будущей автоподборки из каталога (пока без отдельных индексируемых filter-URL).'
                    ))
                    ->schema([
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(191)
                            ->unique(ignoreRecord: true)
                            ->helperText('Сегмент URL: /landings/{slug}. Латиница.'),
                        TextInput::make('title')->required()->maxLength(255)->helperText('Название в админке.'),
                        TextInput::make('h1')->maxLength(255)->helperText('Заголовок на сайте.'),
                        Textarea::make('intro')->rows(3)->columnSpanFull(),
                        Textarea::make('body')->rows(8)->columnSpanFull(),
                        KeyValue::make('criteria_json')
                            ->label('Критерии подборки (JSON-пары)')
                            ->keyLabel('Ключ')
                            ->valueLabel('Значение')
                            ->helperText('Служебные метки для будущей связи с каталогом; не путать с текстом для людей выше.')
                            ->columnSpanFull(),
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
            'index' => Pages\ListSeoLandingPages::route('/'),
            'create' => Pages\CreateSeoLandingPage::route('/create'),
            'edit' => Pages\EditSeoLandingPage::route('/{record}/edit'),
        ];
    }
}
