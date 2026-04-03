<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Forms\Components\TenantPublicImagePicker;
use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

final class GalleryBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'gallery';
    }

    public function label(): string
    {
        return 'Галерея';
    }

    public function description(): string
    {
        return 'Набор изображений с подписями.';
    }

    public function icon(): string
    {
        return 'heroicon-o-photo';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Content;
    }

    public function defaultData(): array
    {
        return [
            'heading' => '',
            'images' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')
                ->label('Заголовок')
                ->maxLength(255)
                ->columnSpanFull(),
            Repeater::make('data_json.images')
                ->label('Изображения')
                ->schema([
                    TenantPublicImagePicker::make('url')
                        ->label('Изображение')
                        ->allowEmpty(false)
                        ->columnSpanFull(),
                    TextInput::make('caption')->label('Подпись')->maxLength(255),
                ])
                ->defaultItems(1)
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.gallery';
    }

    public function previewSummary(array $data): string
    {
        $n = $this->countNestedList($data, 'images');

        return $n > 0 ? $n.' '.self::pluralImages($n) : 'Нет изображений';
    }

    private static function pluralImages(int $n): string
    {
        $m = $n % 100;
        $m10 = $n % 10;

        if ($m >= 11 && $m <= 19) {
            return 'изображений';
        }

        return match ($m10) {
            1 => 'изображение',
            2, 3, 4 => 'изображения',
            default => 'изображений',
        };
    }
}
