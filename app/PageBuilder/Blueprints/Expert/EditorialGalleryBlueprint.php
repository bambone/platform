<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class EditorialGalleryBlueprint extends ExpertSectionBlueprint
{
    public function id(): string
    {
        return 'editorial_gallery';
    }

    public function label(): string
    {
        return 'Expert: Галерея';
    }

    public function description(): string
    {
        return 'Редакторская подборка кадров (URL/слоты после загрузки в медиа).';
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
            'section_heading' => '',
            'section_lead' => '',
            'items' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.section_heading')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            Textarea::make('data_json.section_lead')->label('Лид под заголовком')->rows(2)->columnSpanFull(),
            Repeater::make('data_json.items')
                ->label('Кадры и видео')
                ->schema([
                    Select::make('media_kind')
                        ->label('Тип')
                        ->options(['image' => 'Фото', 'video' => 'Видео'])
                        ->default('image'),
                    TextInput::make('image_url')->label('URL изображения')->maxLength(2048),
                    TextInput::make('video_url')->label('URL видео (MP4)')->maxLength(2048),
                    TextInput::make('poster_url')->label('Постер видео')->maxLength(2048),
                    TextInput::make('caption')->label('Подпись')->maxLength(255),
                ])
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.editorial_gallery';
    }

    public function previewSummary(array $data): string
    {
        $n = $this->countNestedList($data, 'items');

        return $n > 0 ? $n.' изображений' : 'Нет изображений';
    }
}
