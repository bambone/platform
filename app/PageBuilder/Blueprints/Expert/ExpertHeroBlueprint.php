<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

final class ExpertHeroBlueprint extends ExpertSectionBlueprint
{
    public function id(): string
    {
        return 'expert_hero';
    }

    public function label(): string
    {
        return 'Expert: Hero';
    }

    public function description(): string
    {
        return 'Главный блок эксперта: заголовки, CTA, слот медиа.';
    }

    public function icon(): string
    {
        return 'heroicon-o-sparkles';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Basic;
    }

    public function defaultData(): array
    {
        return [
            'heading' => '',
            'subheading' => '',
            'description' => '',
            'primary_cta_label' => '',
            'primary_cta_anchor' => '',
            'secondary_cta_label' => '',
            'secondary_cta_anchor' => '',
            'trust_badges' => [],
            'hero_image_slot' => null,
            'hero_image_url' => '',
            'hero_image_alt' => '',
            'overlay_dark' => true,
            'hero_video_url' => '',
            'hero_video_poster_url' => '',
            'video_trigger_label' => 'Смотреть, как проходят занятия',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')->label('Заголовок')->maxLength(500)->columnSpanFull(),
            Textarea::make('data_json.subheading')->label('Подзаголовок')->rows(2)->columnSpanFull(),
            Textarea::make('data_json.description')->label('Описание')->rows(3)->columnSpanFull(),
            TextInput::make('data_json.primary_cta_label')->label('Текст основной CTA')->maxLength(120),
            TextInput::make('data_json.primary_cta_anchor')->label('Якорь основной CTA (#id)')->maxLength(120),
            TextInput::make('data_json.secondary_cta_label')->label('Текст второй CTA')->maxLength(120),
            TextInput::make('data_json.secondary_cta_anchor')->label('Якорь второй CTA')->maxLength(120),
            Repeater::make('data_json.trust_badges')
                ->label('Бейджи доверия')
                ->schema([
                    TextInput::make('text')->label('Текст')->maxLength(255)->required(),
                ])
                ->columnSpanFull(),
            Toggle::make('data_json.overlay_dark')->label('Тёмный оверлей на фоне'),
            TextInput::make('data_json.hero_image_url')
                ->label('URL фото hero')
                ->maxLength(2048)
                ->helperText('Абсолютный или относительный URL (например /tenants/slug/hero.jpg).')
                ->columnSpanFull(),
            TextInput::make('data_json.hero_image_alt')
                ->label('Alt-текст фото')
                ->maxLength(255)
                ->columnSpanFull(),
            TextInput::make('data_json.hero_video_url')
                ->label('URL видео для модалки (MP4 или прямой файл)')
                ->maxLength(2048)
                ->helperText('Когда появится ролик — вставьте URL; кнопка в hero откроет плеер.')
                ->columnSpanFull(),
            TextInput::make('data_json.hero_video_poster_url')
                ->label('Постер для видео (превью)')
                ->maxLength(2048)
                ->columnSpanFull(),
            TextInput::make('data_json.video_trigger_label')
                ->label('Текст кнопки «смотреть занятие»')
                ->maxLength(120)
                ->default('Смотреть, как проходят занятия'),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.expert_hero';
    }

    public function previewSummary(array $data): string
    {
        $h = $this->stringPreview($data, 'heading', 60);

        return $h !== '' ? $h : 'Пустой hero';
    }
}
