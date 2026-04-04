<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Tenant\PageBuilder\SectionAdminPlainText;
use App\Filament\Tenant\PageBuilder\SectionAdminSummary;
use App\Models\PageSection;
use App\PageBuilder\Contracts\PageSectionBlueprintInterface;
use App\Support\PageRichContent;

abstract class AbstractPageSectionBlueprint implements PageSectionBlueprintInterface
{
    public function supportsTheme(string $themeKey): bool
    {
        return in_array($themeKey, ['default', 'moto'], true);
    }

    protected function countNestedList(array $data, string $key): int
    {
        $items = $data[$key] ?? [];

        return is_array($items) ? count($items) : 0;
    }

    protected function stringPreview(array $data, string $key, int $maxLen = 80): string
    {
        $v = $data[$key] ?? '';

        if (! is_string($v)) {
            return '';
        }
        $v = trim($v);

        return $maxLen > 0 && strlen($v) > $maxLen ? substr($v, 0, $maxLen).'…' : $v;
    }

    public function adminSummary(PageSection $section): SectionAdminSummary
    {
        $data = is_array($section->data_json) ? $section->data_json : [];
        $previewRaw = $this->previewSummary($data);
        $lines = SectionAdminPlainText::splitPreviewToLines($previewRaw);
        $listTitle = trim((string) ($section->title ?? ''));
        $displayTitle = $listTitle !== '' ? $listTitle : $this->label();
        $key = trim((string) ($section->section_key ?? ''));
        $displaySubtitle = $key !== '' ? $key.' · '.$this->label() : $this->label();

        $badges = [];
        $meta = [];
        $previewPlain = SectionAdminPlainText::line($previewRaw);
        $blob = mb_strtolower(implode(' ', $lines) !== '' ? implode(' ', $lines) : $previewPlain);
        $emptyPhrases = ['пустой', 'нет изображений', 'нет вопросов', 'пустой текстовый блок', 'пустой hero'];
        $isEmpty = $blob === '';
        foreach ($emptyPhrases as $phrase) {
            if (str_contains($blob, $phrase)) {
                $isEmpty = true;
                break;
            }
        }
        $warning = $isEmpty ? 'Блок малоинформативен или пустой' : null;

        return new SectionAdminSummary(
            displayTitle: $displayTitle,
            displaySubtitle: $displaySubtitle,
            summaryLines: $lines,
            badges: $badges,
            meta: $meta,
            isEmpty: $isEmpty,
            warning: $warning,
            primaryHeadline: null,
            channels: [],
        );
    }

    /**
     * @return list<string>
     */
    protected static function excerptAsTwoLines(string $richOrHtml, int $maxTotalChars = 260): array
    {
        $plain = PageRichContent::toPlainTextExcerpt($richOrHtml, $maxTotalChars);
        $plain = trim(preg_replace('/\s+/', ' ', $plain) ?? '');
        if ($plain === '') {
            return [];
        }
        $len = strlen($plain);
        if ($len <= 110) {
            return [$plain];
        }
        $target = (int) ($len * 0.48);
        $slice = substr($plain, 0, max(40, $target));
        $sp = strrpos($slice, ' ');
        $breakAt = ($sp !== false && $sp > 35) ? $sp : $target;
        $line1 = trim(substr($plain, 0, $breakAt));
        $line2 = trim(substr($plain, $breakAt));
        if ($line2 === '') {
            return [$line1];
        }
        if (strlen($line2) > 130) {
            $line2 = substr($line2, 0, 127).'…';
        }

        return [$line1, $line2];
    }
}
