<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Forms\Components\TenantPublicImagePicker;
use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class CardsTeaserBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'cards_teaser';
    }

    public function label(): string
    {
        return 'Карточки услуг';
    }

    public function description(): string
    {
        return 'Заголовок, описание и карточки с кнопкой.';
    }

    public function icon(): string
    {
        return 'heroicon-o-rectangle-stack';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Catalog;
    }

    public function defaultData(): array
    {
        return [
            'heading' => '',
            'description' => '',
            'cards' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')
                ->label('Заголовок')
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('data_json.description')
                ->label('Описание секции')
                ->rows(3)
                ->columnSpanFull(),
            Repeater::make('data_json.cards')
                ->label('Карточки')
                ->schema([
                    TextInput::make('title')->label('Заголовок')->required()->maxLength(255),
                    Textarea::make('text')->label('Текст')->rows(3)->columnSpanFull(),
                    TenantPublicImagePicker::make('image')
                        ->label('Изображение')
                        ->columnSpanFull(),
                    TextInput::make('button_text')->label('Текст кнопки')->maxLength(120),
                    TextInput::make('button_url')->label('Ссылка')->url()->maxLength(2048),
                ])
                ->defaultItems(1)
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.cards-teaser';
    }

    public function previewSummary(array $data): string
    {
        $n = $this->countNestedList($data, 'cards');
        $h = $this->stringPreview($data, 'heading', 40);

        return $n > 0 ? ($h !== '' ? $h.' · ' : '').$n.' карточек' : 'Нет карточек';
    }
}
