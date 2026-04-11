<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class ReviewFeedBlueprint extends ExpertSectionBlueprint
{
    public function id(): string
    {
        return 'review_feed';
    }

    public function label(): string
    {
        return 'Expert: Отзывы (таблица)';
    }

    public function description(): string
    {
        return 'Лента из опубликованных отзывов; в секции — заголовок и лимит.';
    }

    public function icon(): string
    {
        return 'heroicon-o-chat-bubble-bottom-center-text';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::SocialProof;
    }

    public function defaultData(): array
    {
        return [
            'heading' => '',
            'subheading' => '',
            'section_id' => '',
            'layout' => 'grid',
            'limit' => 9,
            'category_key' => '',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            Textarea::make('data_json.subheading')->label('Подзаголовок (social proof)')->rows(2)->columnSpanFull(),
            TextInput::make('data_json.section_id')
                ->label('HTML id секции (якорь)')
                ->maxLength(64)
                ->helperText('Например reviews — для ссылки /#reviews'),
            TextInput::make('data_json.limit')->numeric()->label('Лимит')->minValue(1)->maxValue(24)->default(9),
            Select::make('data_json.layout')
                ->label('Вид')
                ->options(['grid' => 'Сетка', 'carousel' => 'Карусель (CSS)'])
                ->default('grid'),
            TextInput::make('data_json.category_key')
                ->label('Фильтр category_key')
                ->maxLength(64)
                ->helperText('Пусто — все категории.'),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.review_feed';
    }

    public function previewSummary(array $data): string
    {
        return 'Отзывы из таблицы · до '.(int) ($data['limit'] ?? 9);
    }
}
