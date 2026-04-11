<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class ProcessStepsBlueprint extends ExpertSectionBlueprint
{
    public function id(): string
    {
        return 'process_steps';
    }

    public function label(): string
    {
        return 'Expert: Этапы работы';
    }

    public function description(): string
    {
        return 'Пошаговый процесс + боковой блок.';
    }

    public function icon(): string
    {
        return 'heroicon-o-list-bullet';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Content;
    }

    public function defaultData(): array
    {
        return [
            'section_heading' => '',
            'aside_title' => '',
            'aside_body' => '',
            'steps' => [],
            'aside_image_url' => '',
            'aside_video_url' => '',
            'aside_video_poster_url' => '',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.section_heading')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            TextInput::make('data_json.aside_image_url')
                ->label('URL фото рядом с блоком (практика, машина)')
                ->maxLength(2048)
                ->columnSpanFull(),
            TextInput::make('data_json.aside_video_url')
                ->label('URL видео рядом с блоком (MP4)')
                ->maxLength(2048)
                ->helperText('Если задано, показывается вместо фото.')
                ->columnSpanFull(),
            TextInput::make('data_json.aside_video_poster_url')
                ->label('Постер для видео')
                ->maxLength(2048)
                ->columnSpanFull(),
            TextInput::make('data_json.aside_title')->label('Боковой блок — заголовок')->maxLength(255),
            Textarea::make('data_json.aside_body')->label('Боковой блок — текст')->rows(3)->columnSpanFull(),
            Repeater::make('data_json.steps')
                ->label('Шаги')
                ->schema([
                    TextInput::make('title')->label('Заголовок')->required()->maxLength(255),
                    Textarea::make('body')->label('Текст')->rows(2)->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.process_steps';
    }

    public function previewSummary(array $data): string
    {
        $n = $this->countNestedList($data, 'steps');

        return $n > 0 ? $n.' шагов' : 'Нет шагов';
    }
}
