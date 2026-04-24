<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints\BlackDuck;

use App\Filament\Forms\Components\TenantPublicImagePicker;
use App\Filament\Tenant\PageBuilder\TeleportedEditorRepeater;
use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;

final class BeforeAfterSliderBlueprint extends BlackDuckSectionBlueprint
{
    public function id(): string
    {
        return 'before_after_slider';
    }

    public function label(): string
    {
        return 'Black Duck: до/после';
    }

    public function description(): string
    {
        return 'Пары «до/после»: выбор файлов в публичном хранилище тенанта (как в других блоках).';
    }

    public function icon(): string
    {
        return 'heroicon-o-arrows-right-left';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::SocialProof;
    }

    public function defaultData(): array
    {
        return [
            'heading' => 'Результаты',
            'pairs' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            Placeholder::make('bd_before_after_source_notice')
                ->label('')
                ->content(
                    new HtmlString(
                        '<p class="text-sm text-zinc-600 dark:text-zinc-400">Загруженные здесь пары <strong>хранятся в секции</strong> (JSON страницы). Команда <code>tenant:black-duck:refresh-content</code> и DB-first импорты <strong>не</strong> подменяют этот блок автоматически, но могут трогать <em>другие</em> секции — не путайте с curated-каталожными proof.</p>'
                    )
                )
                ->columnSpanFull(),
            TextInput::make('data_json.heading')
                ->label('Заголовок')
                ->maxLength(255)
                ->columnSpanFull(),
            TeleportedEditorRepeater::make('data_json.pairs')
                ->label('Пары')
                ->addActionLabel('Добавить пару')
                ->schema([
                    TenantPublicImagePicker::make('before_url')
                        ->label('«До»')
                        ->uploadPublicSiteSubdirectory('site/uploads/page-builder/before-after')
                        ->columnSpanFull(),
                    TenantPublicImagePicker::make('after_url')
                        ->label('«После»')
                        ->uploadPublicSiteSubdirectory('site/uploads/page-builder/before-after')
                        ->columnSpanFull(),
                    TextInput::make('caption')
                        ->label('Подпись')
                        ->maxLength(255),
                ])
                ->columnSpanFull()
                ->defaultItems(0)
                ->collapsible(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.before_after_slider';
    }

    public function previewSummary(array $data): string
    {
        $h = $this->stringPreview($data, 'heading', 50);
        $n = is_array($data['pairs'] ?? null) ? count($data['pairs']) : 0;

        return trim(($h !== '' ? $h : 'Before/After').' · '.(string) $n.' пар');
    }
}
