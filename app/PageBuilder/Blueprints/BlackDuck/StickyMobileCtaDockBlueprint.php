<?php

declare(strict_types=1);

namespace App\PageBuilder\Blueprints\BlackDuck;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

final class StickyMobileCtaDockBlueprint extends BlackDuckSectionBlueprint
{
    public function id(): string
    {
        return 'sticky_mobile_cta_dock';
    }

    public function label(): string
    {
        return 'Black Duck: моб. dock CTA';
    }

    public function description(): string
    {
        return 'Фиксированная панель: звонок, мессенджер, запись, расчёт (показ на мобиле).';
    }

    public function icon(): string
    {
        return 'heroicon-o-device-phone-mobile';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Conversion;
    }

    public function defaultData(): array
    {
        return [
            'enabled' => true,
            'label_call' => 'Позвонить',
            'label_messenger' => 'Написать',
            'label_book' => 'Записаться',
            'label_quote' => 'Рассчитать',
        ];
    }

    public function formComponents(): array
    {
        return [
            Toggle::make('data_json.enabled')
                ->label('Включить панель')
                ->default(true),
            TextInput::make('data_json.label_call')->label('Подпись: звонок')->maxLength(32),
            TextInput::make('data_json.label_messenger')->label('Подпись: мессенджер')->maxLength(32),
            TextInput::make('data_json.label_book')->label('Подпись: запись')->maxLength(32),
            TextInput::make('data_json.label_quote')->label('Подпись: расчёт')->maxLength(32),
            TextInput::make('data_json.book_anchor')
                ->label('Якорь/URL записи (опц.)')
                ->maxLength(256),
            TextInput::make('data_json.quote_anchor')
                ->label('Якорь/URL расчёта (опц.)')
                ->maxLength(256),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.sticky_mobile_cta_dock';
    }

    public function previewSummary(array $data): string
    {
        $on = ! empty($data['enabled']) ? 'вкл' : 'выкл';

        return 'Sticky dock · '.$on;
    }
}
