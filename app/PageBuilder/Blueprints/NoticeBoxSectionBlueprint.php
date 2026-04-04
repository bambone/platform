<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Tenant\Support\TenantPageRichEditor;
use App\PageBuilder\PageSectionCategory;
use App\Support\PageRichContent;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

final class NoticeBoxSectionBlueprint extends AbstractPageSectionBlueprint
{
    public function id(): string
    {
        return 'notice_box';
    }

    public function label(): string
    {
        return 'Важная информация';
    }

    public function description(): string
    {
        return 'Выделенный блок: предупреждение, условие, примечание.';
    }

    public function icon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    public function category(): PageSectionCategory
    {
        return PageSectionCategory::HelpNotices;
    }

    public function defaultData(): array
    {
        return [
            'title' => null,
            'text' => '',
            'tone' => 'info',
        ];
    }

    public function formComponents(): array
    {
        return [
            TextInput::make('data_json.title')
                ->label('Заголовок (необязательно)')
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('data_json.tone')
                ->label('Тон')
                ->options([
                    'info' => 'Информация',
                    'warning' => 'Предупреждение',
                    'success' => 'Успех / позитив',
                    'neutral' => 'Нейтрально',
                ])
                ->native(true)
                ->required(),
            TenantPageRichEditor::enhance(
                RichEditor::make('data_json.text')
                    ->label('Текст')
                    ->required()
                    ->columnSpanFull()
                    ->extraInputAttributes(['class' => 'tenant-page-section-rich-editor'])
            ),
        ];
    }

    public function viewLogicalName(): string
    {
        return 'sections.notice-box';
    }

    public function previewSummary(array $data): string
    {
        $tone = (string) ($data['tone'] ?? 'info');
        $toneLabel = match ($tone) {
            'warning' => 'Предупреждение',
            'success' => 'Важно',
            'neutral' => 'Заметка',
            default => 'Инфо',
        };
        $plain = strip_tags(PageRichContent::toHtml($data['text'] ?? ''));
        $plain = trim(preg_replace('/\s+/', ' ', $plain) ?? '');
        $first = $plain !== '' ? (strlen($plain) > 70 ? substr($plain, 0, 70).'…' : $plain) : '';

        return $first !== '' ? $toneLabel.' · '.$first : $toneLabel;
    }
}
