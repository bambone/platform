<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Tenant\PageBuilder\SectionAdminSummary;
use App\Filament\Tenant\Support\TenantPageRichEditor;
use App\Models\PageSection;
use App\PageBuilder\PageSectionCategory;
use App\Support\PageRichContent;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

final class StructuredTextSectionBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'structured_text';
    }

    public function label(): string
    {
        return 'Структурированный текст';
    }

    public function description(): string
    {
        return 'Большой текстовый блок с форматированием, заголовками и списками.';
    }

    public function icon(): string
    {
        return 'heroicon-o-document-text';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::PageContent;
    }

    public function defaultData(): array
    {
        return [
            'title' => null,
            'content' => '',
            'max_width' => 'prose',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.title')
                ->label('Заголовок (необязательно)')
                ->maxLength(255)
                ->columnSpanFull(),
            TenantPageRichEditor::enhance(
                RichEditor::make('data_json.content')
                    ->label('Текст')
                    ->columnSpanFull()
                    ->extraInputAttributes(['class' => 'tenant-page-section-rich-editor'])
            ),
            Select::make('data_json.max_width')
                ->label('Ширина контента')
                ->options([
                    'prose' => 'Узкая (читаемая колонка)',
                    'wide' => 'Шире',
                    'full' => 'На всю ширину',
                ])
                ->native(true)
                ->required(),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.structured-text';
    }

    public function previewSummary(array $data): string
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title !== '') {
            return $this->stringPreview($data, 'title', 120);
        }
        $plain = strip_tags(PageRichContent::toHtml($data['content'] ?? ''));
        $plain = trim(preg_replace('/\s+/', ' ', $plain) ?? '');

        return $plain !== '' ? (strlen($plain) > 120 ? substr($plain, 0, 120).'…' : $plain) : 'Пустой текстовый блок';
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
        $widthKey = (string) ($data['max_width'] ?? 'prose');
        $widthLabel = match ($widthKey) {
            'wide' => 'Шире',
            'full' => 'На всю ширину',
            default => 'Узкая колонка',
        };
        $badges = [$widthLabel];
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
        $warning = $isEmpty ? 'Почти нет текста в блоке' : null;
        $primaryHeadline = $blockTitle !== '' ? $blockTitle : null;

        return new SectionAdminSummary(
            displayTitle: $displayTitle,
            displaySubtitle: $displaySubtitle,
            summaryLines: $lines,
            badges: $badges,
            meta: ['max_width' => $widthKey],
            isEmpty: $isEmpty,
            warning: $warning,
            primaryHeadline: $primaryHeadline,
            channels: [],
        );
    }
}
