<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class ImportantConditionsBlueprint extends ExpertSectionBlueprint
{
    public function id(): string
    {
        return 'important_conditions';
    }

    public function label(): string
    {
        return 'Expert: Условия';
    }

    public function description(): string
    {
        return 'Юридическая сноска и карточки условий.';
    }

    public function icon(): string
    {
        return 'heroicon-o-document-text';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Content;
    }

    public function defaultData(): array
    {
        return [
            'section_heading' => '',
            'legal_note' => '',
            'cards' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.section_heading')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            Textarea::make('data_json.legal_note')->label('Юридическая сноска')->rows(3)->columnSpanFull(),
            Repeater::make('data_json.cards')
                ->label('Карточки')
                ->schema([
                    TextInput::make('title')->label('Заголовок')->required()->maxLength(255),
                    Textarea::make('body')->label('Текст')->rows(3)->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.important_conditions';
    }

    public function previewSummary(array $data): string
    {
        $n = $this->countNestedList($data, 'cards');

        return $n > 0 ? $n.' карточек условий' : 'Нет карточек';
    }
}
