<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

final class ServiceProgramCardsBlueprint extends ExpertSectionBlueprint
{
    public function id(): string
    {
        return 'service_program_cards';
    }

    public function label(): string
    {
        return 'Expert: Программы (таблица)';
    }

    public function description(): string
    {
        return 'Карточки из сущности «Программы»; в секции только заголовки и лимит.';
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
            'section_heading' => '',
            'section_id' => 'programs',
            'limit' => 12,
            'layout' => 'grid',
            'include_slugs' => '',
            'exclude_slugs' => '',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.section_heading')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            TextInput::make('data_json.section_id')->label('HTML id секции')->maxLength(64),
            TextInput::make('data_json.limit')->numeric()->label('Лимит')->minValue(1)->maxValue(48)->default(12),
            Select::make('data_json.layout')
                ->label('Сетка')
                ->options(['grid' => 'Сетка', 'list' => 'Список'])
                ->default('grid'),
            TextInput::make('data_json.include_slugs')
                ->label('Только slug (через запятую)')
                ->maxLength(500)
                ->helperText('Пусто — все видимые программы.'),
            TextInput::make('data_json.exclude_slugs')
                ->label('Исключить slug (через запятую)')
                ->maxLength(500),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.service_program_cards';
    }

    public function previewSummary(array $data): string
    {
        return 'Данные из таблицы программ · лимит '.(int) ($data['limit'] ?? 12);
    }
}
