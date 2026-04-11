<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

final class ExpertLeadFormBlueprint extends ExpertSectionBlueprint
{
    public function id(): string
    {
        return 'expert_lead_form';
    }

    public function label(): string
    {
        return 'Expert: Форма заявки';
    }

    public function description(): string
    {
        return 'Публичная форма expert_service_inquiry + form_configs по ключу.';
    }

    public function icon(): string
    {
        return 'heroicon-o-paper-airplane';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Conversion;
    }

    public function defaultData(): array
    {
        return [
            'heading' => '',
            'subheading' => '',
            'form_key' => 'expert_lead',
            'section_id' => 'expert-inquiry',
            'sticky_cta_label' => '',
            'trust_chips' => [],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            TextInput::make('data_json.subheading')->label('Подзаголовок')->maxLength(500)->columnSpanFull(),
            TextInput::make('data_json.form_key')
                ->label('Ключ form_configs')
                ->required()
                ->maxLength(64)
                ->default('expert_lead'),
            TextInput::make('data_json.section_id')
                ->label('HTML id блока (якорь)')
                ->maxLength(64)
                ->default('expert-inquiry'),
            TextInput::make('data_json.sticky_cta_label')
                ->label('Текст плавающей кнопки (mobile)')
                ->maxLength(64),
            Repeater::make('data_json.trust_chips')
                ->label('Мини-маркеры доверия над формой')
                ->schema([
                    TextInput::make('text')->label('Текст')->maxLength(120)->required(),
                ])
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.expert_lead_form';
    }

    public function previewSummary(array $data): string
    {
        $k = trim((string) ($data['form_key'] ?? ''));

        return $k !== '' ? 'Форма: '.$k : 'Ключ формы не задан';
    }
}
