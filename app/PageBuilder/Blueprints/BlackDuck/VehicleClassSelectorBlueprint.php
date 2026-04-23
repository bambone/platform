<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints\BlackDuck;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

final class VehicleClassSelectorBlueprint extends BlackDuckSectionBlueprint
{
    public function id(): string
    {
        return 'vehicle_class_selector';
    }

    public function label(): string
    {
        return 'Black Duck: класс авто (UI)';
    }

    public function description(): string
    {
        return 'Сегменты для copy и payload лида; не обязан влиять на слоты в Q1.';
    }

    public function icon(): string
    {
        return 'heroicon-o-truck';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Conversion;
    }

    public function defaultData(): array
    {
        return [
            'heading' => 'Класс автомобиля',
            'options' => [
                ['key' => 'sedan', 'label' => 'Седан'],
                ['key' => 'suv', 'label' => 'SUV / кроссовер'],
                ['key' => 'large', 'label' => 'Крупный / микроавтобус'],
            ],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')
                ->label('Заголовок')
                ->maxLength(200)
                ->columnSpanFull(),
            Repeater::make('data_json.options')
                ->label('Варианты')
                ->schema([
                    TextInput::make('key')
                        ->label('Ключ (латиница)')
                        ->maxLength(32),
                    TextInput::make('label')
                        ->label('Подпись')
                        ->maxLength(64),
                ])
                ->columnSpanFull()
                ->defaultItems(0)
                ->collapsible(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.vehicle_class_selector';
    }

    public function previewSummary(array $data): string
    {
        $n = is_array($data['options'] ?? null) ? count($data['options']) : 0;

        return 'Класс авто · '.(string) $n.' вар.';
    }
}
