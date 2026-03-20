<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

class SeoMetaFields
{
    /**
     * @param  bool  $useTabs  When true, organizes SEO into tabs (Main, Open Graph, Advanced) for compact layout
     */
    public static function make(string $relationship = 'seoMeta', bool $useTabs = false): Section
    {
        $schema = $useTabs
            ? [self::buildTabbedSchema()]
            : self::buildFlatSchema();

        return Section::make('SEO и поиск')
            ->relationship($relationship)
            ->description($useTabs
                ? 'То, что видят поисковики и соцсети. Заголовок и описание часто попадают в сниппет в Google.'
                : 'Настройте, как страница выглядит в поиске и при расшаривании. Если оставить пустым, часть полей может подставиться автоматически из контента.')
            ->schema($schema)
            ->columns(1)
            ->collapsible()
            ->collapsed(false);
    }

    /**
     * @return array<int, Component>
     */
    protected static function buildFlatSchema(): array
    {
        return [
            TextInput::make('meta_title')
                ->label('Заголовок в поиске (title)')
                ->helperText('Коротко и по делу, до ~60 символов. Показывается во вкладке браузера и в выдаче Google.')
                ->maxLength(255)
                ->columnSpanFull()
                ->placeholder('Например: Аренда мотоциклов в Сочи — MotoLevins'),
            Textarea::make('meta_description')
                ->label('Описание в поиске')
                ->helperText('1–2 предложения под заголовком в выдаче. Не влияет на «ключевые слова», но влияет на кликабельность.')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('meta_keywords')
                ->label('Ключевые слова (опционально)')
                ->helperText('Многие поисковики это игнорируют; можно оставить пустым.')
                ->maxLength(255),
            TextInput::make('h1')
                ->label('Заголовок H1 на странице')
                ->helperText('Главный видимый заголовок для посетителя. Может совпадать с title или отличаться.')
                ->maxLength(255),
            TextInput::make('canonical_url')
                ->label('Канонический URL')
                ->helperText('Если страница доступна по нескольким адресам, укажите «главный» URL — так вы избегаете дублей в поиске.')
                ->url()
                ->maxLength(500)
                ->columnSpanFull(),
            TextInput::make('robots')
                ->label('Директива robots')
                ->helperText('Например: index, follow или noindex для скрытия из поиска.')
                ->placeholder('index, follow')
                ->maxLength(100),
            Section::make('Соцсети (Open Graph)')
                ->description('Как ссылка выглядит во ВКонтакте, Telegram, Facebook и т.д.')
                ->schema([
                    TextInput::make('og_title')
                        ->label('Заголовок для превью')
                        ->maxLength(255),
                    Textarea::make('og_description')
                        ->label('Текст превью')
                        ->rows(2),
                    TextInput::make('og_image')
                        ->label('Картинка превью (URL)')
                        ->url()
                        ->maxLength(500),
                    TextInput::make('og_type')
                        ->label('Тип объекта')
                        ->placeholder('website')
                        ->maxLength(50),
                    TextInput::make('twitter_card')
                        ->label('Формат карточки Twitter/X')
                        ->placeholder('summary_large_image')
                        ->maxLength(50),
                ])
                ->columns(2)
                ->collapsible(),
            Toggle::make('is_indexable')
                ->label('Разрешить индексацию')
                ->helperText('Выключите, чтобы страница не попала в поиск (noindex по смыслу).')
                ->default(true),
            Toggle::make('is_followable')
                ->label('Разрешить обход ссылок')
                ->helperText('Обычно включено; выключите, если не хотите передавать вес по ссылкам со страницы.')
                ->default(true),
        ];
    }

    protected static function buildTabbedSchema(): Tabs
    {
        return Tabs::make('SEO')
            ->tabs([
                Tab::make('Поиск')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Заголовок в поиске (title)')
                            ->id('seo-meta-title')
                            ->helperText('До ~60 символов; виден в выдаче и во вкладке браузера.')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('meta_description')
                            ->label('Описание в поиске')
                            ->id('seo-meta-description')
                            ->helperText('Кратко опишите страницу для сниппета.')
                            ->rows(5)
                            ->columnSpanFull(),
                        TextInput::make('meta_keywords')
                            ->label('Ключевые слова (опционально)')
                            ->id('seo-meta-keywords')
                            ->maxLength(255),
                        TextInput::make('h1')
                            ->label('Заголовок H1')
                            ->id('seo-h1')
                            ->maxLength(255),
                        TextInput::make('canonical_url')
                            ->label('Канонический URL')
                            ->id('seo-canonical-url')
                            ->helperText('Основной адрес страницы при дублях URL.')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Tab::make('Соцсети')
                    ->schema([
                        TextInput::make('og_title')->label('Заголовок превью')->id('seo-og-title')->maxLength(255)->columnSpanFull(),
                        Textarea::make('og_description')->label('Текст превью')->id('seo-og-description')->rows(3)->columnSpanFull(),
                        TextInput::make('og_image')->label('Картинка (URL)')->id('seo-og-image')->url()->maxLength(500)->columnSpanFull(),
                        TextInput::make('og_type')->label('Тип')->id('seo-og-type')->placeholder('website')->maxLength(50),
                        TextInput::make('twitter_card')->label('Карточка Twitter/X')->id('seo-twitter-card')->placeholder('summary_large_image')->maxLength(50),
                    ])
                    ->columns(2),
                Tab::make('Дополнительно')
                    ->schema([
                        TextInput::make('robots')
                            ->label('Директива robots')
                            ->id('seo-robots')
                            ->helperText('index, follow или noindex, nofollow.')
                            ->placeholder('index, follow')
                            ->maxLength(100)
                            ->columnSpanFull(),
                        Toggle::make('is_indexable')->label('Разрешить индексацию')->id('seo-is-indexable')->default(true),
                        Toggle::make('is_followable')->label('Разрешить обход ссылок')->id('seo-is-followable')->default(true),
                    ])
                    ->columns(2),
            ])
            ->contained(true)
            ->id('motorcycle-seo-tabs')
            ->persistTabInQueryString('seo-tab');
    }
}
