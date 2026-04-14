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

    /**
     * Приводит `trust_chips` к формату Filament Repeater: список строк `['text' => …]`.
     * Старые данные могли быть массивом строк; без нормализации кнопка «Добавить» в редакторе может не работать.
     *
     * @param  array<string, mixed>  $dataJson
     * @return array<string, mixed>
     */
    public static function normalizeDataJsonForEditor(array $dataJson): array
    {
        $dataJson['trust_chips'] = self::normalizeTrustChipsForRepeater($dataJson['trust_chips'] ?? []);

        return $dataJson;
    }

    /**
     * @return list<array{text: string}>
     */
    private static function normalizeTrustChipsForRepeater(mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (is_string($row)) {
                $out[] = ['text' => $row];

                continue;
            }
            if (is_array($row)) {
                $out[] = ['text' => (string) ($row['text'] ?? '')];
            }
        }

        return array_values($out);
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
                ->helperText('Короткие подписи над полями формы на сайте (например «Адвокатский статус», «Челябинск»). На странице «Контакты» эта полоса не показывается.')
                ->schema([
                    TextInput::make('text')->label('Текст')->maxLength(120)->required(),
                ])
                ->defaultItems(0)
                ->addActionLabel('Добавить маркер')
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
