<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Tenant\PageBuilder\SectionAdminSummary;
use App\Filament\Tenant\Support\TenantPageRichEditor;
use App\Models\PageSection;
use App\PageBuilder\PageSectionCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;

final class ContentFaqSectionBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'content_faq';
    }

    public function label(): string
    {
        return 'FAQ';
    }

    public function description(): string
    {
        return 'Вопросы и ответы для информационной страницы.';
    }

    public function icon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::HelpNotices;
    }

    public function defaultData(): array
    {
        return [
            'title' => 'Частые вопросы',
            'items' => [
                ['question' => '', 'answer' => ''],
            ],
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.title')
                ->label('Заголовок секции')
                ->maxLength(255)
                ->columnSpanFull(),
            Repeater::make('data_json.items')
                ->label('Вопросы и ответы')
                ->schema([
                    TextInput::make('question')
                        ->label('Вопрос')
                        ->required()
                        ->maxLength(500)
                        ->columnSpanFull(),
                    TenantPageRichEditor::enhance(
                        RichEditor::make('answer')
                            ->label('Ответ')
                            ->required()
                            ->columnSpanFull()
                            ->extraInputAttributes(['class' => 'tenant-page-section-rich-editor']),
                        withAttachmentHelp: false
                    ),
                ])
                ->defaultItems(1)
                ->columnSpanFull(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.content-faq';
    }

    public function previewSummary(array $data): string
    {
        $n = $this->countNestedList($data, 'items');

        return $n > 0 ? 'FAQ · '.$n.' '.self::pluralQuestions($n) : 'Нет вопросов';
    }

    public function adminSummary(PageSection $section): SectionAdminSummary
    {
        $data = is_array($section->data_json) ? $section->data_json : [];
        $label = $this->label();
        $listTitle = trim((string) ($section->title ?? ''));
        $secTitle = trim((string) ($data['title'] ?? ''));
        $displayTitle = $listTitle !== '' ? $listTitle : ($secTitle !== '' ? $secTitle : $label);
        $items = $data['items'] ?? [];
        $n = is_array($items) ? count($items) : 0;
        $lines = [];
        if ($n > 0) {
            $lines[] = $n.' '.self::pluralQuestions($n);
            $shown = 0;
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $q = trim((string) ($item['question'] ?? ''));
                if ($q === '') {
                    continue;
                }
                $lines[] = '• '.$q;
                $shown++;
                if ($shown >= 2) {
                    break;
                }
            }
        } else {
            $lines[] = 'Нет вопросов';
        }
        $key = trim((string) ($section->section_key ?? ''));
        $displaySubtitle = $key !== '' ? $key.' · '.$label : $label;
        $isEmpty = $n === 0;
        $warning = $isEmpty ? 'Нет ни одного вопроса' : null;

        return new SectionAdminSummary(
            displayTitle: $displayTitle,
            displaySubtitle: $displaySubtitle,
            summaryLines: array_slice($lines, 0, 5),
            badges: $n > 0 ? ['FAQ'] : [],
            meta: ['faq_count' => (string) $n],
            isEmpty: $isEmpty,
            warning: $warning,
            primaryHeadline: $secTitle !== '' ? $secTitle : null,
            channels: [],
        );
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
