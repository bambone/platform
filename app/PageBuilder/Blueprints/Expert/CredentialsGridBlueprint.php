<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class CredentialsGridBlueprint extends ExpertSectionBlueprint
{
    public function id(): string
    {
        return 'credentials_grid';
    }

    public function label(): string
    {
        return 'Expert: Достижения / доверие';
    }

    public function description(): string
    {
        return 'Сетка аргументов доверия, опциональный слот фона.';
    }

    public function icon(): string
    {
        return 'heroicon-o-trophy';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::SocialProof;
    }

    public function defaultData(): array
    {
        return [
            'section_heading' => '',
            'lead' => '',
            'items' => [],
            'background_media_slot' => null,
            'background_image_url' => '',
            'supporting_image_url' => '',
            'supporting_image_alt' => '',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.section_heading')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            Textarea::make('data_json.lead')->label('Лид под заголовком')->rows(2)->columnSpanFull(),
            Repeater::make('data_json.items')
                ->label('Пункты')
                ->schema([
                    TextInput::make('title')->label('Заголовок')->required()->maxLength(255),
                    Textarea::make('description')->label('Описание')->rows(2)->columnSpanFull(),
                ])
                ->columnSpanFull(),
            TextInput::make('data_json.background_image_url')
                ->label('URL фона секции')
                ->maxLength(2048)
                ->helperText('Опционально: фото трассы / автомобиля под лёгким затемнением.')
                ->columnSpanFull(),
            TextInput::make('data_json.supporting_image_url')
                ->label('Фото рядом с текстом (награждение, экипировка)')
                ->maxLength(2048)
                ->columnSpanFull(),
            TextInput::make('data_json.supporting_image_alt')
                ->label('Alt для фото рядом')
                ->maxLength(255)
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.credentials_grid';
    }

    public function previewSummary(array $data): string
    {
        $n = $this->countNestedList($data, 'items');

        return $n > 0 ? $n.' пунктов' : 'Пустая сетка';
    }
}
