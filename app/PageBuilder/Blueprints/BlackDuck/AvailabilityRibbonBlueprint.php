<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints\BlackDuck;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class AvailabilityRibbonBlueprint extends BlackDuckSectionBlueprint
{
    public function id(): string
    {
        return 'availability_ribbon';
    }

    public function label(): string
    {
        return 'Black Duck: лента срочности/окон';
    }

    public function description(): string
    {
        return 'Короткий информирующий текст (не заменяет реальные слоты; см. Precedence).';
    }

    public function icon(): string
    {
        return 'heroicon-o-bell-alert';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Conversion;
    }

    public function defaultData(): array
    {
        return [
            'text' => '',
        ];
    }

    public function formComponents(): array
    {
        return [
            Textarea::make('data_json.text')
                ->label('Текст')
                ->rows(2)
                ->columnSpanFull(),
            TextInput::make('data_json.aria_label')
                ->label('ARIA-метка')
                ->maxLength(200),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.availability_ribbon';
    }

    public function previewSummary(array $data): string
    {
        return $this->stringPreview($data, 'text', 80) ?: 'Availability ribbon';
    }
}
