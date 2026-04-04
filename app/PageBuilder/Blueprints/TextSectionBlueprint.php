<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Tenant\PageBuilder\SectionAdminSummary;
use App\Filament\Tenant\Support\TenantPageRichEditor;
use App\Models\PageSection;
use App\PageBuilder\PageSectionCategory;
use App\Support\PageRichContent;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;

final class TextSectionBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'text_section';
    }

    public function label(): string
    {
        return 'Текстовый раздел';
    }

    public function description(): string
    {
        return 'Заголовок и текст отдельного смыслового блока.';
    }

    public function icon(): string
    {
        return 'heroicon-o-queue-list';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::PageContent;
    }

    public function defaultData(): array
    {
        return [
            'title' => 'Новый раздел',
            'content' => '',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.title')
                ->label('Заголовок раздела')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            TenantPageRichEditor::enhance(
                RichEditor::make('data_json.content')
                    ->label('Текст')
                    ->required()
                    ->columnSpanFull()
                    ->extraInputAttributes(['class' => 'tenant-page-section-rich-editor'])
            ),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.text-section';
    }

    public function previewSummary(array $data): string
    {
        $t = $this->stringPreview($data, 'title', 80);
        $plain = strip_tags(PageRichContent::toHtml($data['content'] ?? ''));
        $plain = trim(preg_replace('/\s+/', ' ', $plain) ?? '');
        $snippet = $plain !== '' ? (strlen($plain) > 60 ? substr($plain, 0, 60).'…' : $plain) : '';

        return trim($t.($snippet !== '' ? ' · '.$snippet : ''));
    }

    public function adminSummary(PageSection $section): SectionAdminSummary
    {
        $data = is_array($section->data_json) ? $section->data_json : [];
        $listTitle = trim((string) ($section->title ?? ''));
        $blockTitle = trim((string) ($data['title'] ?? ''));
        $label = $this->label();
        $displayTitle = $listTitle !== '' ? $listTitle : ($blockTitle !== '' ? $blockTitle : $label);
        $html = PageRichContent::toHtml($data['content'] ?? '');
        $lines = self::excerptAsTwoLines($data['content'] ?? '', 280);
        $badges = [];
        if ($html !== '' && stripos($html, '<img') !== false) {
            $badges[] = 'Изображения';
        }
        if ($html !== '' && stripos($html, '<table') !== false) {
            $badges[] = 'Таблица';
        }
        if ($html !== '' && (stripos($html, '<ul') !== false || stripos($html, '<ol') !== false)) {
            $badges[] = 'Список';
        }
        $key = trim((string) ($section->section_key ?? ''));
        $displaySubtitle = $key !== '' ? $key.' · '.$label : $label;
        $plainExcerpt = PageRichContent::toPlainTextExcerpt($data['content'] ?? '', 400);
        $isEmpty = trim($plainExcerpt) === '' && $blockTitle === '';
        $warning = $isEmpty ? 'Нет заголовка раздела и текста' : null;
        $primaryHeadline = $blockTitle !== '' ? $blockTitle : null;

        return new SectionAdminSummary(
            displayTitle: $displayTitle,
            displaySubtitle: $displaySubtitle,
            summaryLines: $lines,
            badges: $badges,
            meta: [],
            isEmpty: $isEmpty,
            warning: $warning,
            primaryHeadline: $primaryHeadline,
            channels: [],
        );
    }
}
