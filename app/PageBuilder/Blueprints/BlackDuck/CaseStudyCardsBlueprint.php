<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints\BlackDuck;

use App\Filament\Forms\Components\TenantPublicImagePicker;
use App\Filament\Tenant\PageBuilder\TeleportedEditorRepeater;
use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;

final class CaseStudyCardsBlueprint extends BlackDuckSectionBlueprint
{
    public function id(): string
    {
        return 'case_study_cards';
    }

    public function label(): string
    {
        return 'Black Duck: кейсы';
    }

    public function description(): string
    {
        return 'Карточки работ: авто, задача, срок, результат.';
    }

    public function icon(): string
    {
        return 'heroicon-o-photo';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::SocialProof;
    }

    public function defaultData(): array
    {
        return [
            'heading' => 'Работы',
            'items' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            Placeholder::make('bd_case_study_source_notice')
                ->label('')
                ->content(
                    new HtmlString(
                        '<p class="text-sm text-zinc-600 dark:text-zinc-400">Кейсы <strong>редактируются здесь</strong> (секция page builder). Сетка «Работы» и curated proof из БД/импорта — <strong>отдельный</strong> поток: <code>refresh-content</code> и каталог не заменяют эти поля, пока не меняется сама страница.</p>'
                    )
                )
                ->columnSpanFull(),
            TextInput::make('data_json.heading')
                ->label('Заголовок')
                ->maxLength(200)
                ->columnSpanFull(),
            TeleportedEditorRepeater::make('data_json.items')
                ->label('Кейсы')
                ->addActionLabel('Добавить кейс')
                ->schema([
                    TextInput::make('vehicle')
                        ->label('Авто')
                        ->maxLength(200),
                    TextInput::make('task')
                        ->label('Задача')
                        ->maxLength(400),
                    TextInput::make('duration')
                        ->label('Срок')
                        ->maxLength(120),
                    TextInput::make('result')
                        ->label('Результат')
                        ->maxLength(400),
                    TenantPublicImagePicker::make('image_url')
                        ->label('Фото')
                        ->uploadPublicSiteSubdirectory('site/uploads/page-builder/case-study')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull()
                ->defaultItems(0)
                ->collapsible(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.case_study_cards';
    }

    public function previewSummary(array $data): string
    {
        $h = $this->stringPreview($data, 'heading', 50);
        $n = is_array($data['items'] ?? null) ? count($data['items']) : 0;

        return trim(($h !== '' ? $h : 'Кейсы').' · '.(string) $n);
    }
}
