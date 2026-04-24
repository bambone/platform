<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints\BlackDuck;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;

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
        return 'Сетка карточек **генерируется** при сохранении каталога услуг (БД) и командой `tenant:black-duck:refresh-content` из `tenant_service_programs`. Список и группы ниже в редакторе **не** задают витрину: правки — в «Программах/услугах».';
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
            Placeholder::make('bd_service_hub_source_notice')
                ->label('')
                ->content(
                    new HtmlString(
                        '<p class="text-sm text-zinc-600 dark:text-zinc-400">Карточки и <strong>группы</strong> на /uslugi и главной подставляются из каталога в БД. Поля <code>items</code> / <code>groups</code> в JSON не редактируются здесь, чтобы не путать с витриной — «Программы/услуги» и автоматическое обновление после сохранения.</p>'
                    )
                )
                ->columnSpanFull(),
            TextInput::make('data_json.heading')
                ->label('Заголовок секции')
                ->helperText('Виден на сайте; сетка карточек берётся из каталога услуг, не из прежнего «репитера».')
                ->maxLength(255)
                ->columnSpanFull(),
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
