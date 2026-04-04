<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Tenant\PageBuilder\SectionAdminSummary;
use App\Models\PageSection;
use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

final class DataTableSectionBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'data_table';
    }

    public function label(): string
    {
        return 'Таблица';
    }

    public function description(): string
    {
        return 'Тарифы, условия, параметры в табличном виде.';
    }

    public function icon(): string
    {
        return 'heroicon-o-table-cells';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::StructureLists;
    }

    public function defaultData(): array
    {
        return [
            'title' => null,
            'columns' => [
                ['name' => 'Параметр'],
                ['name' => 'Значение'],
            ],
            'rows' => [
                ['cells' => [['value' => ''], ['value' => '']]],
            ],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.title')
                ->label('Заголовок (необязательно)')
                ->maxLength(255)
                ->columnSpanFull(),
            Repeater::make('data_json.columns')
                ->label('Колонки (слева направо)')
                ->schema([
                    TextInput::make('name')
                        ->label('Заголовок колонки')
                        ->required()
                        ->maxLength(255),
                ])
                ->defaultItems(2)
                ->minItems(1)
                ->columnSpanFull(),
            Repeater::make('data_json.rows')
                ->label('Строки')
                ->schema([
                    Repeater::make('cells')
                        ->label('Ячейки строки')
                        ->schema([
                            TextInput::make('value')
                                ->label('Значение')
                                ->maxLength(2000),
                        ])
                        ->defaultItems(2)
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.data-table';
    }

    public function previewSummary(array $data): string
    {
        $rows = $data['rows'] ?? [];

        return is_array($rows) ? 'Таблица · '.count($rows).' '.self::pluralRows(count($rows)) : 'Таблица';
    }

    public function adminSummary(PageSection $section): SectionAdminSummary
    {
        $data = is_array($section->data_json) ? $section->data_json : [];
        $label = $this->label();
        $listTitle = trim((string) ($section->title ?? ''));
        $displayTitle = $listTitle !== '' ? $listTitle : $label;
        $rows = $data['rows'] ?? [];
        $n = is_array($rows) ? count($rows) : 0;
        $lines = [$n > 0 ? $n.' '.self::pluralRows($n) : 'Нет строк'];
        $key = trim((string) ($section->section_key ?? ''));
        $displaySubtitle = $key !== '' ? $key.' · '.$label : $label;

        return new SectionAdminSummary(
            displayTitle: $displayTitle,
            displaySubtitle: $displaySubtitle,
            summaryLines: $lines,
            badges: ['Таблица'],
            meta: ['row_count' => (string) $n],
            isEmpty: $n === 0,
            warning: $n === 0 ? 'В таблице нет строк' : null,
            primaryHeadline: null,
            channels: [],
        );
    }

    private static function pluralRows(int $n): string
    {
        $m = $n % 100;
        $m10 = $n % 10;
        if ($m >= 11 && $m <= 19) {
            return 'строк';
        }

        return match ($m10) {
            1 => 'строка',
            2, 3, 4 => 'строки',
            default => 'строк',
        };
    }
}
