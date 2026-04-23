<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints\BlackDuck;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

final class PackageMatrixBlueprint extends BlackDuckSectionBlueprint
{
    public function id(): string
    {
        return 'package_matrix';
    }

    public function label(): string
    {
        return 'Black Duck: матрица пакетов';
    }

    public function description(): string
    {
        return 'Сравнение пакетов (базовый / оптимум / премиум).';
    }

    public function icon(): string
    {
        return 'heroicon-o-table-cells';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Catalog;
    }

    public function defaultData(): array
    {
        return [
            'heading' => 'Пакеты',
            'columns' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')
                ->label('Заголовок')
                ->maxLength(200)
                ->columnSpanFull(),
            Repeater::make('data_json.columns')
                ->label('Колонки (пакеты)')
                ->schema([
                    TextInput::make('name')
                        ->label('Название пакета')
                        ->maxLength(120),
                    TextInput::make('price_hint')
                        ->label('Цена / подсказка')
                        ->maxLength(80),
                ])
                ->columnSpanFull()
                ->defaultItems(0)
                ->collapsible(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.package_matrix';
    }

    public function previewSummary(array $data): string
    {
        $h = $this->stringPreview($data, 'heading', 50);
        $n = is_array($data['columns'] ?? null) ? count($data['columns']) : 0;

        return trim(($h !== '' ? $h : 'Matrix').' · '.(string) $n.' кол.');
    }
}
