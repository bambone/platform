<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints\BlackDuck;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

final class MessengerCaptureBarBlueprint extends BlackDuckSectionBlueprint
{
    public function id(): string
    {
        return 'messenger_capture_bar';
    }

    public function label(): string
    {
        return 'Black Duck: панель мессенджеров';
    }

    public function description(): string
    {
        return 'Быстрые кнопки WhatsApp, Telegram, звонок (URL из настроек контактов, если не задано).';
    }

    public function icon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Contacts;
    }

    public function defaultData(): array
    {
        return [
            'show_whatsapp' => true,
            'show_telegram' => true,
            'show_call' => true,
            'title' => 'Связаться быстро',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.title')
                ->label('Заголовок')
                ->maxLength(200)
                ->columnSpanFull(),
            Toggle::make('data_json.show_whatsapp')->label('WhatsApp')->default(true),
            Toggle::make('data_json.show_telegram')->label('Telegram')->default(true),
            Toggle::make('data_json.show_call')->label('Звонок')->default(true),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.messenger_capture_bar';
    }

    public function previewSummary(array $data): string
    {
        return $this->stringPreview($data, 'title', 60) ?: 'Messenger bar';
    }
}
