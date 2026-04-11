<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class PricingCardsBlueprint extends ExpertSectionBlueprint
{
    public function id(): string
    {
        return 'pricing_cards';
    }

    public function label(): string
    {
        return 'Expert: Цены (из программ)';
    }

    public function description(): string
    {
        return 'Карточки цен из видимых программ; в секции — заголовки и фильтры slug.';
    }

    public function icon(): string
    {
        return 'heroicon-o-banknotes';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Conversion;
    }

    public function defaultData(): array
    {
        return [
            'heading' => '',
            'subheading' => '',
            'layout' => 'grid',
            'note' => '',
            'include_slugs' => '',
            'exclude_slugs' => '',
            'manual_cards' => [],
            'entry_point_slug' => 'single-session',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            Textarea::make('data_json.subheading')->label('Подзаголовок')->rows(2)->columnSpanFull(),
            Select::make('data_json.layout')
                ->label('Сетка')
                ->options(['grid' => 'Сетка', 'compact' => 'Компактно'])
                ->default('grid'),
            Textarea::make('data_json.note')->label('Примечание / дисклеймер')->rows(2)->columnSpanFull(),
            TextInput::make('data_json.include_slugs')
                ->label('Только slug (через запятую)')
                ->maxLength(500),
            TextInput::make('data_json.exclude_slugs')
                ->label('Исключить slug (через запятую)')
                ->maxLength(500),
            TextInput::make('data_json.entry_point_slug')
                ->label('Slug программы «стартовая точка» (подсветка)')
                ->maxLength(128)
                ->default('single-session')
                ->helperText('Например single-session — занятие по вождению.'),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.pricing_cards';
    }

    public function previewSummary(array $data): string
    {
        return 'Цены из программ · '.($this->stringPreview($data, 'heading', 40) ?: 'без заголовка');
    }
}
