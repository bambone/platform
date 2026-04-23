<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints\BlackDuck;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

final class ServiceHubGridBlueprint extends BlackDuckSectionBlueprint
{
    public function id(): string
    {
        return 'service_hub_grid';
    }

    public function label(): string
    {
        return 'Black Duck: сетка услуг (hub)';
    }

    public function description(): string
    {
        return 'Карточки услуг с ценой «от», сроком и метками онлайн/по подтверждению.';
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
            'heading' => 'Услуги',
            'items' => [],
            'groups' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')
                ->label('Заголовок секции')
                ->maxLength(255)
                ->columnSpanFull(),
            Repeater::make('data_json.items')
                ->label('Карточки')
                ->schema([
                    TextInput::make('title')
                        ->label('Название')
                        ->maxLength(255),
                    TextInput::make('price_from')
                        ->label('Цена «от»')
                        ->maxLength(64),
                    TextInput::make('duration')
                        ->label('Срок')
                        ->maxLength(64),
                    Toggle::make('online_booking')
                        ->label('Онлайн-запись')
                        ->default(false),
                    Toggle::make('needs_confirmation')
                        ->label('По подтверждению')
                        ->default(false),
                    TextInput::make('cta_url')
                        ->label('Ссылка CTA')
                        ->maxLength(2048),
                    TextInput::make('image_url')
                        ->label('URL изображения (или путь site/brand/… в хранилище тенанта)')
                        ->maxLength(2048),
                ])
                ->columnSpanFull()
                ->defaultItems(0)
                ->collapsible(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.service_hub_grid';
    }

    public function previewSummary(array $data): string
    {
        $h = $this->stringPreview($data, 'heading', 50);
        $n = is_array($data['items'] ?? null) ? count($data['items']) : 0;
        $g = is_array($data['groups'] ?? null) ? count($data['groups']) : 0;
        $suffix = $g > 0 ? (string) $g.' групп' : (string) $n.' карт.';

        return trim(($h !== '' ? $h : 'Service hub').' · '.$suffix);
    }
}
