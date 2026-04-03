<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Forms\Components\PageBuilderIconPicker;
use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class FeaturesBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'features';
    }

    public function label(): string
    {
        return 'Преимущества';
    }

    public function description(): string
    {
        return 'Сетка карточек: иконка/ключ, заголовок, описание.';
    }

    public function icon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Content;
    }

    public function defaultData(): array
    {
        return [
            'section_heading' => '',
            'items' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.section_heading')
                ->label('Заголовок секции')
                ->maxLength(255)
                ->columnSpanFull(),
            Repeater::make('data_json.items')
                ->label('Карточки')
                ->schema([
                    PageBuilderIconPicker::make('icon')
                        ->label('Иконка')
                        ->catalogGroup('features')
                        ->helperText('Необязательно: показывается на сайте слева от заголовка.'),
                    TextInput::make('title')->label('Заголовок')->required()->maxLength(255),
                    Textarea::make('description')->label('Описание')->rows(3)->columnSpanFull(),
                ])
                ->defaultItems(1)
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.features';
    }

    public function previewSummary(array $data): string
    {
        $n = $this->countNestedList($data, 'items');
        $h = $this->stringPreview($data, 'section_heading', 40);

        return $n > 0 ? ($h !== '' ? $h.' · ' : '').$n.' '.self::pluralCards($n) : 'Нет карточек';
    }

    private static function pluralCards(int $n): string
    {
        $m = $n % 100;
        $m10 = $n % 10;

        if ($m >= 11 && $m <= 19) {
            return 'карточек';
        }

        return match ($m10) {
            1 => 'карточка',
            2, 3, 4 => 'карточки',
            default => 'карточек',
        };
    }
}
