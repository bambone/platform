<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints\BlackDuck;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

final class BeforeAfterSliderBlueprint extends BlackDuckSectionBlueprint
{
    public function id(): string
    {
        return 'before_after_slider';
    }

    public function label(): string
    {
        return 'Black Duck: до/после';
    }

    public function description(): string
    {
        return 'Пары изображений до и после (URL в хранилище).';
    }

    public function icon(): string
    {
        return 'heroicon-o-arrows-right-left';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::SocialProof;
    }

    public function defaultData(): array
    {
        return [
            'heading' => 'Результаты',
            'pairs' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')
                ->label('Заголовок')
                ->maxLength(255)
                ->columnSpanFull(),
            Repeater::make('data_json.pairs')
                ->label('Пары')
                ->schema([
                    TextInput::make('before_url')
                        ->label('URL «до»')
                        ->maxLength(2048),
                    TextInput::make('after_url')
                        ->label('URL «после»')
                        ->maxLength(2048),
                    TextInput::make('caption')
                        ->label('Подпись')
                        ->maxLength(255),
                ])
                ->columnSpanFull()
                ->defaultItems(0)
                ->collapsible(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.before_after_slider';
    }

    public function previewSummary(array $data): string
    {
        $h = $this->stringPreview($data, 'heading', 50);
        $n = is_array($data['pairs'] ?? null) ? count($data['pairs']) : 0;

        return trim(($h !== '' ? $h : 'Before/After').' · '.(string) $n.' пар');
    }
}
