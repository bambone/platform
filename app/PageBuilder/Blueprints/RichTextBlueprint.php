<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Tenant\PageBuilder\SectionAdminSummary;
use App\Filament\Tenant\Support\TenantPageRichEditor;
use App\Models\PageSection;
use App\PageBuilder\PageSectionCategory;
use App\Support\PageRichContent;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;

final class RichTextBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'rich_text';
    }

    public function label(): string
    {
        return 'Текстовый блок';
    }

    public function description(): string
    {
        return 'Заголовок и форматированный текст.';
    }

    public function icon(): string
    {
        return 'heroicon-o-document-text';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::Content;
    }

    public function defaultData(): array
    {
        return [
            'heading' => '',
            'content' => '',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.heading')
                ->label('Заголовок секции')
                ->maxLength(255)
                ->columnSpanFull(),
            TenantPageRichEditor::enhance(
                RichEditor::make('data_json.content')
                    ->label('Текст')
                    ->columnSpanFull()
                    ->extraInputAttributes(['class' => 'tenant-page-section-rich-editor'])
            ),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.rich-text';
    }

    public function previewSummary(array $data): string
    {
        $h = $this->stringPreview($data, 'heading', 50);
        $c = strip_tags(PageRichContent::toHtml($data['content'] ?? ''));
        $c = trim(preg_replace('/\s+/', ' ', $c) ?? '');

        return $h !== '' ? $h : ($c !== '' ? substr($c, 0, 80).'…' : 'Пустой текст');
    }

    public function adminSummary(PageSection $section): SectionAdminSummary
    {
        $data = is_array($section->data_json) ? $section->data_json : [];
        $label = $this->label();
        $listTitle = trim((string) ($section->title ?? ''));
        $heading = trim((string) ($data['heading'] ?? ''));
        $displayTitle = $listTitle !== '' ? $listTitle : ($heading !== '' ? $heading : $label);
        $lines = self::excerptAsTwoLines($data['content'] ?? '', 280);
        $html = PageRichContent::toHtml($data['content'] ?? '');
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
        $isEmpty = trim($plainExcerpt) === '' && $heading === '';
        $warning = $isEmpty ? 'Нет заголовка и текста' : null;

        return new SectionAdminSummary(
            displayTitle: $displayTitle,
            displaySubtitle: $displaySubtitle,
            summaryLines: $lines !== [] ? $lines : ($isEmpty ? ['Пустой блок'] : []),
            badges: $badges,
            meta: [],
            isEmpty: $isEmpty,
            warning: $warning,
            primaryHeadline: $heading !== '' ? $heading : null,
            channels: [],
        );
    }
}
