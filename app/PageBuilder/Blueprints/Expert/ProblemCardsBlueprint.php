<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

final class ProblemCardsBlueprint extends ExpertSectionBlueprint
{
    public function id(): string
    {
        return 'problem_cards';
    }

    public function label(): string
    {
        return 'Expert: Запросы / проблемы';
    }

    public function description(): string
    {
        return 'Карточки «с чем приходят» + решение.';
    }

    public function icon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Content;
    }

    public function defaultData(): array
    {
        return [
            'section_heading' => '',
            'footnote' => '',
            'accent_image_url' => '',
            'items' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.section_heading')->label('Заголовок секции')->maxLength(255)->columnSpanFull(),
            Textarea::make('data_json.footnote')->label('Сноска под блоком')->rows(2)->columnSpanFull(),
            TextInput::make('data_json.accent_image_url')
                ->label('Акцентное фото секции (фон)')
                ->maxLength(2048)
                ->helperText('Опционально: атмосферное фото слева/на фоне.')
                ->columnSpanFull(),
            Repeater::make('data_json.items')
                ->label('Карточки')
                ->schema([
                    TextInput::make('title')->label('Заголовок')->required()->maxLength(255),
                    Textarea::make('description')->label('Проблема')->rows(2)->columnSpanFull(),
                    Textarea::make('solution')->label('Что меняется / как решаем')->rows(2)->columnSpanFull(),
                    Toggle::make('is_featured')->label('Выделить карточку'),
                ])
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.problem_cards';
    }

    public function previewSummary(array $data): string
    {
        $n = $this->countNestedList($data, 'items');

        return $n > 0 ? $n.' карточек' : 'Нет карточек';
    }
}
