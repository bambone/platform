<?php

namespace App\PageBuilder\Blueprints;

use App\Filament\Tenant\PageBuilder\SectionAdminPlainText;
use App\Filament\Tenant\PageBuilder\SectionAdminSummary;
use App\Models\PageSection;
use App\PageBuilder\Contracts\PageSectionBlueprintInterface;
use App\Support\PageRichContent;
use Filament\Forms\Components\TextInput;

abstract class AbstractPageSectionBlueprint implements PageSectionBlueprintInterface
{
    /**
     * Поле «якорь»: в публичной вёрстке на обёртку секции вешается HTML-атрибут id (ссылки вида /страница#идентификатор).
     */
    protected static function makeSectionHtmlIdTextInput(
        string $label = 'HTML id секции (якорь)',
    ): TextInput {
        return TextInput::make('data_json.section_id')
            ->label($label)
            ->maxLength(64)
            ->helperText('Ссылка на этот блок с той же страницы: в конец адреса добавьте #и_идентификатор, например …/o-trener#programs. Только латиница, цифры и дефис, без пробелов. Пусто — без якоря.')
            ->hintIcon('heroicon-o-information-circle')
            ->hintIconTooltip('Идентификатор попадает в разметку как id у секции: браузер прокручивает страницу к этому месту. Используют внутренние ссылки, кнопки «Перейти к разделу» и редко — стили или скрипты. На одной странице не должно быть двух секций с одним и тем же id.');
    }

    public function supportsTheme(string $themeKey): bool
    {
        return in_array($themeKey, ['default', 'moto', 'advocate_editorial', 'black_duck'], true);
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
