<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Forms\Components\TenantPublicImagePicker;
use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

final class HeroBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return 'Hero';
    }

    public function description(): string
    {
        return 'Главный баннер: видео (постер + источник), тексты, опционально фон-картинка для других шаблонов.';
    }

    public function icon(): string
    {
        return 'heroicon-o-photo';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Basic;
    }

    public function defaultData(): array
    {
        return [
            'variant' => 'full_background',
            'heading' => '',
            'subheading' => '',
            'description' => '',
            'video_poster' => '',
            'video_src' => '',
            'button_text' => '',
            'button_url' => '',
            'background_image' => '',
            'overlay_dark' => true,
            'chips' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            Select::make('data_json.variant')
                ->label('Вариант')
                ->options([
                    'full_background' => 'Фон на всю ширину',
                    'image_right' => 'Картинка справа',
                    'compact' => 'Компактный',
                ])
                ->default('full_background')
                ->native(true),
            TextInput::make('data_json.heading')
                ->label('Заголовок')
                ->maxLength(255)
                ->columnSpanFull(),
            TextInput::make('data_json.subheading')
                ->label('Подзаголовок')
                ->maxLength(500)
                ->columnSpanFull(),
            Textarea::make('data_json.description')
                ->label('Описание (под баннером)')
                ->rows(2)
                ->columnSpanFull(),
            TenantPublicImagePicker::make('data_json.video_poster')
                ->label('Постер видео')
                ->helperText(__('Обложка для фона hero. Пусто — постер из общей темы (bundled).'))
                ->uploadPublicSiteSubdirectory('site/videos')
                ->columnSpanFull(),
            TextInput::make('data_json.video_src')
                ->label('Видео hero (MP4)')
                ->placeholder('site/videos/…')
                ->maxLength(2048)
                ->helperText(__('Заглушка: пусто — на сайте нет кнопки «Смотреть видео». Укажите путь к файлу в вашем хранилище (например site/videos/имя.mp4 после загрузки) или полный https-URL. Общий ролик из шаблона темы не подставляется.'))
                ->columnSpanFull(),
            TextInput::make('data_json.button_text')
                ->label('Текст кнопки')
                ->maxLength(120),
            TextInput::make('data_json.button_url')
                ->label('Ссылка кнопки')
                ->url()
                ->maxLength(2048),
            TenantPublicImagePicker::make('data_json.background_image')
                ->label('Фоновое изображение')
                ->themeFallbackPreviewPath('marketing/hero-bg.png')
                ->helperText(__('Пустое поле — на сайте используется фон из темы. Свой файл сохраняется в вашем каталоге (S3); файлы темы удалить нельзя.'))
                ->columnSpanFull(),
            Toggle::make('data_json.overlay_dark')
                ->label('Затемнение фона')
                ->default(true),
            Repeater::make('data_json.chips')
                ->label('Короткие преимущества (чипы)')
                ->schema([
                    TextInput::make('text')->label('Текст')->required()->maxLength(120),
                ])
                ->defaultItems(0)
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.hero';
    }

    public function previewSummary(array $data): string
    {
        $h = $this->stringPreview($data, 'heading', 60);
        $btn = $this->stringPreview($data, 'button_text', 40);
        $parts = array_filter([$h, $btn ? 'Кнопка: '.$btn : '']);

        return $parts !== [] ? implode(' · ', $parts) : 'Пустой hero';
    }
}
