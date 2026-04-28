<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

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
        return 'Карточки из «Программы» (тексты и фон каждой — в разделе Программы). Здесь — заголовок секции, подзаголовок и лимит.';
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
            'section_lead' => 'Модули обучения под конкретную задачу: от городского комфорта до зимней безопасности и спорта.',
            'section_id' => 'programs',
            'limit' => 12,
            'layout' => 'grid',
            'include_slugs' => '',
            'exclude_slugs' => '',
            'uniform_columns' => false,
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.section_heading')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            Textarea::make('data_json.section_lead')
                ->label('Подзаголовок под заголовком')
                ->rows(3)
                ->maxLength(2000)
                ->helperText('Пустое поле — на сайте подзаголовок не показывается.')
                ->columnSpanFull(),
            static::makeSectionHtmlIdTextInput(),
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
            Toggle::make('data_json.uniform_columns')
                ->label('Ровная сетка карточек')
                ->helperText('Вкл.: все карточки одной ширины (без «флагмана» на весь ряд). Удобно для отдельной страницы «Программы».')
                ->default(false),
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
