<?php

namespace App\PageBuilder\Blueprints;

use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

final class FaqBlueprint extends AbstractPageSectionBlueprint
{
    public function supportsTheme(string $themeKey): bool
    {
        return in_array($themeKey, ['default', 'moto', 'expert_auto', 'advocate_editorial'], true);
    }

    public function id(): string
    {
        return 'faq';
    }

    public function label(): string
    {
        return 'FAQ';
    }

    public function description(): string
    {
        return 'Список вопросов и ответов.';
    }

    public function icon(): string
    {
        return 'heroicon-o-question-mark-circle';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Content;
    }

    public function defaultData(): array
    {
        return [
            'section_heading' => '',
            'items' => [],
            'source' => '',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.section_heading')
                ->label('Заголовок секции')
                ->maxLength(255)
                ->columnSpanFull(),
            Toggle::make('data_json.source')
                ->label('Брать вопросы из раздела FAQ (таблица, show_on_home)')
                ->dehydrated(true)
                ->formatStateUsing(fn (?string $state): bool => $state === 'faqs_table')
                ->dehydrateStateUsing(fn (bool $state): string => $state ? 'faqs_table' : '')
                ->helperText('Если включено, список ниже не используется на сайте — выводятся опубликованные FAQ с признаком «на главной».'),
            Repeater::make('data_json.items')
                ->label('Вопросы и ответы')
                ->schema([
                    TextInput::make('question')->label('Вопрос')->required()->maxLength(500)->columnSpanFull(),
                    Textarea::make('answer')->label('Ответ')->required()->rows(4)->columnSpanFull(),
                ])
                ->defaultItems(1)
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.faq';
    }

    public function previewSummary(array $data): string
    {
        $n = $this->countNestedList($data, 'items');

        return $n > 0 ? $n.' '.self::pluralQuestions($n) : 'Нет вопросов';
    }

    private static function pluralQuestions(int $n): string
    {
        $m = $n % 100;
        $m10 = $n % 10;

        if ($m >= 11 && $m <= 19) {
            return 'вопросов';
        }

        return match ($m10) {
            1 => 'вопрос',
            2, 3, 4 => 'вопроса',
            default => 'вопросов',
        };
    }
}
