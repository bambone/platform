<?php

namespace App\PageBuilder\Blueprints\Expert;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

final class EnrollmentCtaStripBlueprint extends ExpertSectionBlueprint
{
    public function id(): string
    {
        return 'enrollment_cta_strip';
    }

    public function label(): string
    {
        return 'Expert: Блок записи (CTA)';
    }

    public function description(): string
    {
        return 'Заметный призыв к записи вверху страницы: та же логика modal / страница / якорь, что и у карточек программ.';
    }

    public function icon(): string
    {
        return 'heroicon-o-rocket-launch';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Content;
    }

    public function defaultData(): array
    {
        return [
            'section_id' => '',
            'heading' => 'Запишитесь на занятие',
            'lead' => 'Оставьте заявку — подберём формат и согласуем удобное время.',
            'button_label' => 'Записаться на занятие',
            'source_context' => 'enrollment_cta_strip',
            'goal_prefill' => 'Хочу записаться на индивидуальное занятие.',
        ];
    }

    public function formComponents(): array
    {
        return [
            static::makeSectionHtmlIdTextInput(),
            TextInput::make('data_json.heading')->label('Заголовок')->maxLength(255)->columnSpanFull(),
            Textarea::make('data_json.lead')->label('Подзаголовок')->rows(2)->columnSpanFull(),
            TextInput::make('data_json.button_label')->label('Текст кнопки')->maxLength(120),
            TextInput::make('data_json.source_context')->label('Метка источника (analytics)')
                ->maxLength(120)
                ->helperText('Например programs_page_strip, o_trener_hero. Попадает в payload_json заявки.'),
            Textarea::make('data_json.goal_prefill')->label('Предзаполнение «цель обращения» в модалке')
                ->rows(2)
                ->maxLength(500)
                ->columnSpanFull(),
            Toggle::make('data_json.enabled')->label('Показывать блок')->default(true),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.enrollment_cta_strip';
    }

    public function previewSummary(array $data): string
    {
        $h = trim((string) ($data['heading'] ?? ''));

        return $h !== '' ? $h : 'Блок записи';
    }
}
