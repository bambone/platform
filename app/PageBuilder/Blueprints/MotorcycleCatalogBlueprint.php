<?php

namespace App\PageBuilder\Blueprints;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Сетка каталога мотоциклов на главной (данные байков с контроллера; в конструкторе — только заголовки).
 */
final class MotorcycleCatalogBlueprint extends AbstractPageSectionBlueprint
{
    public function supportsTheme(string $themeKey): bool
    {
        return in_array($themeKey, ['default', 'moto'], true);
    }

    public function id(): string
    {
        return 'motorcycle_catalog';
    }

    public function label(): string
    {
        return 'Каталог мотоциклов';
    }

    public function description(): string
    {
        return 'Сетка техники с фильтрами по датам (как на главной). Порядок относительно других блоков задаётся стрелками в конструкторе.';
    }

    public function icon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Catalog;
    }

    public function defaultData(): array
    {
        return [
            'heading' => 'Наш автопарк',
            'subheading' => 'Премиальная техника для любого стиля. Ограниченное количество мотоциклов — бронируйте заранее.',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')
                ->label('Заголовок блока')
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('data_json.subheading')
                ->label('Подзаголовок')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.motorcycle-catalog';
    }

    public function previewSummary(array $data): string
    {
        return $this->stringPreview($data, 'heading', 80) ?: 'Каталог мотоциклов';
    }
}
