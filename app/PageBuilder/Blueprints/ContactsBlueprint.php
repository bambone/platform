<?php

namespace App\PageBuilder\Blueprints;

use App\PageBuilder\PageSectionCategory;
use App\Support\RussianPhone;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class ContactsBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'contacts';
    }

    public function label(): string
    {
        return 'Контакты';
    }

    public function description(): string
    {
        return 'Телефон, мессенджеры, адрес, карта.';
    }

    public function icon(): string
    {
        return 'heroicon-o-phone';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Contacts;
    }

    public function defaultData(): array
    {
        return [
            'heading' => '',
            'description' => '',
            'phone' => '',
            'whatsapp' => '',
            'telegram' => '',
            'address' => '',
            'map_embed_html' => '',
            'map_url' => '',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')
                ->label('Заголовок')
                ->maxLength(255)
                ->columnSpanFull(),
            Textarea::make('data_json.description')
                ->label('Описание')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('data_json.phone')
                ->label('Телефон')
                ->tel()
                ->telRegex(RussianPhone::filamentTelDisplayRegex())
                ->maxLength(64),
            TextInput::make('data_json.whatsapp')
                ->label('WhatsApp (ссылка или номер)')
                ->maxLength(255),
            TextInput::make('data_json.telegram')
                ->label('Telegram (username или ссылка)')
                ->maxLength(255),
            Textarea::make('data_json.address')
                ->label('Адрес')
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('data_json.map_embed_html')
                ->label('Карта (HTML iframe, опционально)')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('data_json.map_url')
                ->label('Ссылка на карту')
                ->url()
                ->maxLength(2048),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.contacts';
    }

    public function previewSummary(array $data): string
    {
        $phone = trim((string) ($data['phone'] ?? ''));
        $addr = $this->stringPreview($data, 'address', 50);

        return trim(implode(' · ', array_filter([$phone, $addr], fn (string $s): bool => $s !== ''))) ?: 'Контакты не заполнены';
    }
}
