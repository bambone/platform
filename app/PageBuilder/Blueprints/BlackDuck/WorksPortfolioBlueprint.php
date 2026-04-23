<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints\BlackDuck;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * /raboty: сетка портфолио, фильтры и lightbox (данные синхронизируются из {@see \App\Tenant\BlackDuck\BlackDuckMediaCatalog}).
 */
final class WorksPortfolioBlueprint extends BlackDuckSectionBlueprint
{
    public function id(): string
    {
        return 'works_portfolio';
    }

    public function label(): string
    {
        return 'Black Duck: портфолио (/raboty)';
    }

    public function description(): string
    {
        return 'Галерея работ с фильтрами; наполнение из media-catalog.json.';
    }

    public function icon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::SocialProof;
    }

    public function defaultData(): array
    {
        return [
            'heading' => 'Портфолио',
            'intro' => '',
            'filters' => [],
            'gallery_items' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')
                ->label('Заголовок')
                ->maxLength(200)
                ->columnSpanFull(),
            Textarea::make('data_json.intro')
                ->label('Вводный текст')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.works_portfolio';
    }

    public function previewSummary(array $data): string
    {
        $h = $this->stringPreview($data, 'heading', 40);
        $n = is_array($data['gallery_items'] ?? null) ? count($data['gallery_items']) : 0;

        return trim(($h !== '' ? $h : 'Портфолио').' · '.(string) $n);
    }
}
