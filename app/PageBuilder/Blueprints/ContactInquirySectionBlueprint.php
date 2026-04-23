<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * Публичная форма обратной связи для страницы «Контакты» (AF-003): CRM pipeline + настройки в builder.
 */
final class ContactInquirySectionBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'contact_inquiry';
    }

    public function supportsTheme(string $themeKey): bool
    {
        return in_array($themeKey, ['default', 'moto', 'advocate_editorial', 'expert_auto', 'black_duck'], true);
    }

    public function label(): string
    {
        return 'Форма на странице контактов';
    }

    public function description(): string
    {
        return 'Заявка в CRM с маршрута /contacts: имя, телефон, сообщение; опционально email и способ связи.';
    }

    public function icon(): string
    {
        return 'heroicon-o-envelope';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Contacts;
    }

    public function defaultData(): array
    {
        return [
            'enabled' => true,
            'heading' => 'Напишите нам',
            'subheading' => 'Оставьте сообщение, и мы свяжемся с вами удобным способом.',
            'expectation_note' => '',
            'message_label' => 'Сообщение',
            'submit_label' => 'Отправить сообщение',
            'success_message' => 'Спасибо! Мы получили ваше сообщение и свяжемся с вами.',
            'section_id' => 'contact-inquiry',
            'show_email' => true,
            'show_preferred_channel' => true,
            'consent_enabled' => false,
            'consent_label' => 'Я согласен(на) на обработку персональных данных.',
        ];
    }

    public function formComponents(): array
    {
        return [
            Toggle::make('data_json.enabled')
                ->label('Показывать форму на сайте')
                ->default(true)
                ->columnSpanFull(),
            TextInput::make('data_json.heading')
                ->label('Заголовок')
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('data_json.subheading')
                ->label('Подзаголовок')
                ->maxLength(600)
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('data_json.expectation_note')
                ->label('Текст «что будет после отправки» (под полями, перед кнопкой)')
                ->maxLength(800)
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('data_json.message_label')
                ->label('Подпись к полю с текстом обращения')
                ->maxLength(120)
                ->columnSpanFull(),
            TextInput::make('data_json.submit_label')
                ->label('Текст кнопки')
                ->maxLength(120)
                ->columnSpanFull(),
            Textarea::make('data_json.success_message')
                ->label('Сообщение после успешной отправки')
                ->maxLength(500)
                ->rows(2)
                ->columnSpanFull(),
            TextInput::make('data_json.section_id')
                ->label('HTML id блока (якорь)')
                ->maxLength(64)
                ->default('contact-inquiry'),
            Toggle::make('data_json.show_email')
                ->label('Поле email')
                ->default(true),
            Toggle::make('data_json.show_preferred_channel')
                ->label('Предпочитаемый способ связи')
                ->default(true),
            Toggle::make('data_json.consent_enabled')
                ->label('Чекбокс согласия')
                ->default(false),
            Textarea::make('data_json.consent_label')
                ->label('Текст согласия')
                ->maxLength(500)
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.contact_inquiry';
    }

    public function previewSummary(array $data): string
    {
        $h = trim((string) ($data['heading'] ?? ''));

        return $h !== '' ? $h : 'Форма контактов';
    }
}
