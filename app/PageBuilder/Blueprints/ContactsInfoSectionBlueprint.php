<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Tenant\PageBuilder\SectionAdminSummary;
use App\Models\PageSection;
use App\PageBuilder\PageSectionCategory;
use App\Support\RussianPhone;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class ContactsInfoSectionBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'contacts_info';
    }

    public function label(): string
    {
        return 'Контакты';
    }

    public function description(): string
    {
        return 'Карточки связи, адрес, режим работы и карта. Все поля необязательны — пустые не показываются на сайте.';
    }

    public function icon(): string
    {
        return 'heroicon-o-map-pin';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Contacts;
    }

    public function defaultData(): array
    {
        return [
            'title' => 'Контакты',
            'description' => null,
            'phone' => null,
            'email' => null,
            'whatsapp' => null,
            'telegram' => null,
            'address' => null,
            'working_hours' => null,
            'map_embed' => null,
            'map_link' => null,
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.title')
                ->label('Заголовок')
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('data_json.description')
                ->label('Короткий текст под заголовком')
                ->helperText('Например, когда лучше звонить или как добраться до точки выдачи.')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('data_json.phone')
                ->label('Телефон')
                ->tel()
                ->telRegex(RussianPhone::filamentTelDisplayRegex())
                ->maxLength(64)
                ->helperText('Отображается как ссылка для набора (tel:).'),
            TextInput::make('data_json.email')
                ->label('Email')
                ->email()
                ->maxLength(255),
            TextInput::make('data_json.whatsapp')
                ->label('WhatsApp')
                ->maxLength(255)
                ->helperText('Номер цифрами, без «+» и пробелов (например 79131234567) — для кнопки чата wa.me.'),
            TextInput::make('data_json.telegram')
                ->label('Telegram')
                ->maxLength(255)
                ->helperText('Имя пользователя без @ — для ссылки t.me.'),
            Textarea::make('data_json.address')
                ->label('Адрес')
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('data_json.working_hours')
                ->label('Режим работы')
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('data_json.map_embed')
                ->label('Карта (HTML iframe, опционально)')
                ->helperText('Код вставки с Яндекс.Карт, Google Maps и т.п. Если пусто — можно указать только ссылку ниже.')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('data_json.map_link')
                ->label('Ссылка на карту')
                ->url()
                ->maxLength(2048)
                ->helperText('Откроется в новой вкладке; при отсутствии iframe некоторые ссылки можно подставить как src (если сервис разрешает).'),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.contacts-info';
    }

    public function previewSummary(array $data): string
    {
        $phone = trim((string) ($data['phone'] ?? ''));
        $addr = $this->stringPreview($data, 'address', 40);
        $email = trim((string) ($data['email'] ?? ''));
        $parts = array_filter([$phone, $addr, $email], fn (string $s): bool => $s !== '');

        return $parts !== [] ? implode(' · ', $parts) : 'Контакты';
    }

    public function adminSummary(PageSection $section): SectionAdminSummary
    {
        $data = is_array($section->data_json) ? $section->data_json : [];
        $label = $this->label();
        $listTitle = trim((string) ($section->title ?? ''));
        $blockTitle = trim((string) ($data['title'] ?? ''));
        $displayTitle = $listTitle !== '' ? $listTitle : ($blockTitle !== '' ? $blockTitle : $label);
        $phone = trim((string) ($data['phone'] ?? ''));
        $wa = trim((string) ($data['whatsapp'] ?? ''));
        $tg = trim((string) ($data['telegram'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $addr = trim((string) ($data['address'] ?? ''));
        $hours = trim((string) ($data['working_hours'] ?? ''));
        $lines = [];
        if ($addr !== '') {
            $lines[] = 'Адрес: '.$this->stringPreview($data, 'address', 140);
        }
        if ($hours !== '') {
            $lines[] = 'Часы: '.$this->stringPreview($data, 'working_hours', 100);
        }
        $key = trim((string) ($section->section_key ?? ''));
        $displaySubtitle = $key !== '' ? $key.' · '.$label : $label;
        $hasChannel = $phone !== '' || $wa !== '' || $tg !== '' || $email !== '';
        $isEmpty = ! $hasChannel && $addr === '';
        $warning = $isEmpty ? 'Нет телефона, мессенджеров и email' : null;
        $channels = [
            ['icon' => 'heroicon-o-phone', 'label' => 'Телефон', 'on' => $phone !== ''],
            ['icon' => 'heroicon-o-chat-bubble-left-ellipsis', 'label' => 'WhatsApp', 'on' => $wa !== ''],
            ['icon' => 'heroicon-o-paper-airplane', 'label' => 'Telegram', 'on' => $tg !== ''],
            ['icon' => 'heroicon-o-envelope', 'label' => 'Email', 'on' => $email !== ''],
            ['icon' => 'heroicon-o-map-pin', 'label' => 'Адрес', 'on' => $addr !== ''],
        ];
        $primaryHeadline = $blockTitle !== '' ? $blockTitle : null;

        return new SectionAdminSummary(
            displayTitle: $displayTitle,
            displaySubtitle: $displaySubtitle,
            summaryLines: $lines !== [] ? $lines : ($isEmpty ? ['Каналы не заполнены'] : []),
            badges: [],
            meta: [],
            isEmpty: $isEmpty,
            warning: $warning,
            primaryHeadline: $primaryHeadline,
            channels: $channels,
        );
    }
}
