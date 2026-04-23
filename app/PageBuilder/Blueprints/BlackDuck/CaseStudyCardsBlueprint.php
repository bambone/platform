<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints\BlackDuck;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

final class CaseStudyCardsBlueprint extends BlackDuckSectionBlueprint
{
    public function id(): string
    {
        return 'case_study_cards';
    }

    public function label(): string
    {
        return 'Black Duck: кейсы';
    }

    public function description(): string
    {
        return 'Карточки работ: авто, задача, срок, результат.';
    }

    public function icon(): string
    {
        return 'heroicon-o-photo';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::SocialProof;
    }

    public function defaultData(): array
    {
        return [
            'heading' => 'Работы',
            'items' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')
                ->label('Заголовок')
                ->maxLength(200)
                ->columnSpanFull(),
            Repeater::make('data_json.items')
                ->label('Кейсы')
                ->schema([
                    TextInput::make('vehicle')
                        ->label('Авто')
                        ->maxLength(200),
                    TextInput::make('task')
                        ->label('Задача')
                        ->maxLength(400),
                    TextInput::make('duration')
                        ->label('Срок')
                        ->maxLength(120),
                    TextInput::make('result')
                        ->label('Результат')
                        ->maxLength(400),
                    TextInput::make('image_url')
                        ->label('URL фото')
                        ->maxLength(2048),
                ])
                ->columnSpanFull()
                ->defaultItems(0)
                ->collapsible(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.case_study_cards';
    }

    public function previewSummary(array $data): string
    {
        $h = $this->stringPreview($data, 'heading', 50);
        $n = is_array($data['items'] ?? null) ? count($data['items']) : 0;

        return trim(($h !== '' ? $h : 'Кейсы').' · '.(string) $n);
    }
}
