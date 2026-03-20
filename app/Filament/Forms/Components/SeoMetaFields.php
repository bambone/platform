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

        return Section::make('SEO')
            ->relationship($relationship)
            ->description($useTabs ? 'Мета-теги и настройки индексации' : null)
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
                ->label('Meta Title')
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('meta_description')
                ->label('Meta Description')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('meta_keywords')
                ->label('Meta Keywords')
                ->maxLength(255),
            TextInput::make('h1')
                ->label('H1')
                ->maxLength(255),
            TextInput::make('canonical_url')
                ->label('Canonical URL')
                ->url()
                ->maxLength(500)
                ->columnSpanFull(),
            TextInput::make('robots')
                ->label('Robots')
                ->placeholder('index, follow')
                ->maxLength(100),
            Section::make('Open Graph')
                ->schema([
                    TextInput::make('og_title')->label('OG Title')->maxLength(255),
                    Textarea::make('og_description')->label('OG Description')->rows(2),
                    TextInput::make('og_image')->label('OG Image URL')->url()->maxLength(500),
                    TextInput::make('og_type')->label('OG Type')->placeholder('website')->maxLength(50),
                    TextInput::make('twitter_card')->label('Twitter Card')->placeholder('summary_large_image')->maxLength(50),
                ])
                ->columns(2)
                ->collapsible(),
            Toggle::make('is_indexable')->label('Индексировать')->default(true),
            Toggle::make('is_followable')->label('Следовать по ссылкам')->default(true),
        ];
    }

    protected static function buildTabbedSchema(): Tabs
    {
        return Tabs::make('SEO')
            ->tabs([
                Tab::make('Основное SEO')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta Title')
                            ->id('seo-meta-title')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->id('seo-meta-description')
                            ->rows(5)
                            ->columnSpanFull(),
                        TextInput::make('meta_keywords')
                            ->label('Meta Keywords')
                            ->id('seo-meta-keywords')
                            ->maxLength(255),
                        TextInput::make('h1')
                            ->label('H1')
                            ->id('seo-h1')
                            ->maxLength(255),
                        TextInput::make('canonical_url')
                            ->label('Canonical URL')
                            ->id('seo-canonical-url')
                            ->url()
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Tab::make('Open Graph')
                    ->schema([
                        TextInput::make('og_title')->label('OG Title')->id('seo-og-title')->maxLength(255)->columnSpanFull(),
                        Textarea::make('og_description')->label('OG Description')->id('seo-og-description')->rows(3)->columnSpanFull(),
                        TextInput::make('og_image')->label('OG Image URL')->id('seo-og-image')->url()->maxLength(500)->columnSpanFull(),
                        TextInput::make('og_type')->label('OG Type')->id('seo-og-type')->placeholder('website')->maxLength(50),
                        TextInput::make('twitter_card')->label('Twitter Card')->id('seo-twitter-card')->placeholder('summary_large_image')->maxLength(50),
                    ])
                    ->columns(2),
                Tab::make('Advanced')
                    ->schema([
                        TextInput::make('robots')
                            ->label('Robots')
                            ->id('seo-robots')
                            ->placeholder('index, follow')
                            ->maxLength(100)
                            ->columnSpanFull(),
                        Toggle::make('is_indexable')->label('Индексировать')->id('seo-is-indexable')->default(true),
                        Toggle::make('is_followable')->label('Следовать по ссылкам')->id('seo-is-followable')->default(true),
                    ])
                    ->columns(2),
            ])
            ->contained(true)
            ->id('motorcycle-seo-tabs')
            ->persistTabInQueryString('seo-tab');
    }
}
