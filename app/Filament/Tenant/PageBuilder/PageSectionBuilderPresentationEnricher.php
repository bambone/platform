<?php

namespace App\Filament\Tenant\PageBuilder;

use App\Models\Page;
use App\Models\PageSection;
use App\PageBuilder\Contacts\ContactChannelsResolver;
use App\PageBuilder\Contacts\ContactMapCanonical;

/**
 * Слой согласования карточки секции в админке с тем, как блок реально выводится на публичной странице
 * (без дублирования полного фронтенда).
 */
final class PageSectionBuilderPresentationEnricher
{
    public function enrich(
        SectionAdminSummary $summary,
        Page $page,
        PageSection $section,
        string $typeId,
        string $themeKey,
    ): SectionAdminSummary {
        $slug = (string) ($page->slug ?? '');
        $pageMode = $this->pageMode($slug);
        $data = is_array($section->data_json) ? $section->data_json : [];

        $onSite = '';
        $notes = [];
        $presentation = [
            'page_mode' => $pageMode,
            'theme_key' => $themeKey,
            'type_id' => $typeId,
        ];

        switch ($typeId) {
            case 'structured_text':
                [$onSite, $n, $p] = $this->structuredTextPresentation($themeKey, $slug, $data);
                $notes = $n;
                $presentation = array_merge($presentation, $p);
                break;
            case 'text_section':
                [$onSite, $n, $p] = $this->textSectionPresentation($themeKey, $slug, $pageMode);
                $notes = $n;
                $presentation = array_merge($presentation, $p);
                break;
            case 'hero':
                [$onSite, $n, $p] = $this->heroPresentation($themeKey, $slug, $pageMode, $data);
                $notes = $n;
                $presentation = array_merge($presentation, $p);
                break;
            case 'contacts_info':
                [$onSite, $n, $p] = $this->contactsInfoPresentation($themeKey, $slug, $pageMode, $data);
                $notes = $n;
                $presentation = array_merge($presentation, $p);
                break;
            case 'content_faq':
                [$onSite, $n, $p] = $this->faqPresentation($themeKey);
                $notes = $n;
                $presentation = array_merge($presentation, $p);
                break;
            case 'faq':
                [$onSite, $n, $p] = $this->faqPresentation($themeKey);
                $notes = $n;
                $presentation = array_merge($presentation, $p);
                break;
            case 'gallery':
                [$onSite, $n, $p] = $this->galleryPresentation($themeKey);
                $notes = $n;
                $presentation = array_merge($presentation, $p);
                break;
            case 'data_table':
                [$onSite, $n, $p] = $this->dataTablePresentation($themeKey, $data);
                $notes = $n;
                $presentation = array_merge($presentation, $p);
                break;
            case 'rich_text':
                [$onSite, $n, $p] = $this->richTextPresentation($themeKey, $pageMode);
                $notes = $n;
                $presentation = array_merge($presentation, $p);
                break;
            default:
                $presentation['render_mode'] = 'generic_block';
                $notes[] = 'Карточка в админке упрощена: на сайте действуют отступы и типографика темы.';
                break;
        }

        return $summary->withPublicLayer($onSite, $notes, $presentation);
    }

    private function pageMode(string $slug): string
    {
        return match ($slug) {
            'home' => 'home',
            'contacts' => 'contacts',
            'usloviya-arenda' => 'rules_doc',
            default => 'content',
        };
    }

    /**
     * @return array{0: string, 1: list<string>, 2: array<string, string>}
     */
    private function structuredTextPresentation(string $themeKey, string $slug, array $data): array
    {
        $maxWidth = (string) ($data['max_width'] ?? 'prose');
        $widthLabel = match ($maxWidth) {
            'wide' => 'широкая колонка',
            'full' => 'на всю ширину контента',
            default => 'узкая читаемая колонка',
        };

        $notes = [];
        $presentation = [
            'render_mode' => 'structured_rich',
            'width_mode' => $maxWidth,
        ];

        if ($themeKey === 'moto' && $slug === 'contacts') {
            return [
                'На сайте: выделенный блок-пояснение (callout) с акцентной полосой, перед блоком контактов.',
                array_merge($notes, [
                    'На странице «Контакты» этот тип оформляется не как обычная карточка правил.',
                ]),
                array_merge($presentation, [
                    'render_mode' => 'moto_contacts_callout',
                    'theme_variant' => 'moto_callout',
                ]),
            ];
        }

        if ($themeKey === 'moto' && $slug === 'usloviya-arenda') {
            return [
                'На сайте: карточка раздела в колонке рядом с боковым оглавлением правил.',
                array_merge($notes, [
                    'Заголовок в карточке на сайте берётся из поля блока или подписи секции.',
                ]),
                array_merge($presentation, [
                    'render_mode' => 'moto_policy_card_sidebar',
                    'theme_variant' => 'moto_policy_card',
                ]),
            ];
        }

        if ($themeKey === 'moto') {
            return [
                'На сайте: карточка раздела (как на странице условий), в общем потоке страницы.',
                $notes,
                array_merge($presentation, [
                    'render_mode' => 'moto_policy_card',
                    'theme_variant' => 'moto_policy_card',
                ]),
            ];
        }

        return [
            'На сайте: текстовый блок в колонке ('.$widthLabel.'), оформление темы «'.$themeKey.'».',
            $notes,
            array_merge($presentation, [
                'render_mode' => 'default_structured',
                'theme_variant' => 'default_prose',
            ]),
        ];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: array<string, string>}
     */
    private function textSectionPresentation(string $themeKey, string $slug, string $pageMode): array
    {
        if ($themeKey === 'moto') {
            $line = $pageMode === 'rules_doc'
                ? 'На сайте: карточка раздела в колонке рядом с оглавлением (как структурированный текст).'
                : 'На сайте: карточка раздела с заголовком и текстом в потоке страницы.';

            return [
                $line,
                [],
                [
                    'render_mode' => $pageMode === 'rules_doc' ? 'moto_policy_card_sidebar' : 'moto_policy_card',
                    'width_mode' => 'full',
                    'theme_variant' => 'moto_policy_card',
                ],
            ];
        }

        return [
            'На сайте: раздел с заголовком и текстом в ширине темы «'.$themeKey.'».',
            [],
            ['render_mode' => 'text_section', 'theme_variant' => $themeKey],
        ];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: array<string, string>}
     */
    private function heroPresentation(string $themeKey, string $slug, string $pageMode, array $data): array
    {
        $variant = (string) ($data['variant'] ?? 'full_background');
        $isCompact = $variant === 'compact';
        $bg = trim((string) ($data['background_image'] ?? ''));
        $video = trim((string) ($data['video_src'] ?? '')) !== '' || trim((string) ($data['video_poster'] ?? '')) !== '';

        $notes = [];
        if ($themeKey === 'moto' && ! $isCompact && $bg === '') {
            $notes[] = 'Фон не задан — на сайте подставится фон из темы (как на витрине).';
        }
        if ($isCompact) {
            $notes[] = 'Компактный вариант — без полноэкранного фона, блок в виде карточки.';
        }

        $line = '';
        if ($themeKey === 'moto' && $isCompact && $pageMode === 'rules_doc') {
            $line = 'На сайте: компактный баннер-ввод вверху документа правил.';
        } elseif ($isCompact) {
            $line = 'На сайте: компактный баннер в карточке.';
        } else {
            $line = 'На сайте: широкий баннер'.($video ? ' с возможностью видео/постера' : '').'.';
        }

        return [
            $line,
            $notes,
            [
                'render_mode' => $isCompact ? 'hero_compact' : 'hero_full',
                'width_mode' => $isCompact ? 'card' : 'full',
                'theme_variant' => $themeKey === 'moto' ? 'moto_hero' : 'default_hero',
            ],
        ];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: array<string, string>}
     */
    private function contactsInfoPresentation(string $themeKey, string $slug, string $pageMode, array $data): array
    {
        $notes = [];
        $hasMap = ContactMapCanonical::fromDataJson($data)->hasVisibleMap();
        if (! $hasMap) {
            $notes[] = 'Карта на сайте не покажется, пока не включена карта и не указана безопасная ссылка (Яндекс / Google / 2ГИС).';
        }

        $analysis = app(ContactChannelsResolver::class)->analyze($data);
        foreach ($analysis->warnings as $w) {
            $notes[] = $w;
        }
        if ($analysis->usableCount > 0) {
            $notes[] = 'На витрине сейчас '.$analysis->usableCount.' активных канал(ов) связи; адрес, часы и карта — по заполнению полей блока.';
        }

        $line = $slug === 'contacts'
            ? 'На сайте: блок контактов в сетке страницы «Контакты» (каналы, адрес, часы, карта по заполнению).'
            : 'На сайте: карточка контактов в теле страницы; пустые поля не отображаются.';

        return [
            $line,
            $notes,
            [
                'render_mode' => $slug === 'contacts' ? 'contacts_page_grid' : 'contacts_embedded',
                'theme_variant' => $themeKey === 'moto' ? 'moto_contacts' : 'default_contacts',
            ],
        ];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: array<string, string>}
     */
    private function faqPresentation(string $themeKey): array
    {
        return [
            'На сайте: список вопросов с раскрытием ответов (оформление темы).',
            [],
            [
                'render_mode' => 'faq_accordion',
                'theme_variant' => $themeKey.'_faq',
            ],
        ];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: array<string, string>}
     */
    private function galleryPresentation(string $themeKey): array
    {
        return [
            'На сайте: сетка изображений с подписями (если заданы).',
            [],
            [
                'render_mode' => 'gallery_grid',
                'theme_variant' => $themeKey.'_gallery',
            ],
        ];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: array<string, string>}
     */
    private function dataTablePresentation(string $themeKey, array $data): array
    {
        $cols = $data['columns'] ?? [];
        $colCount = is_array($cols) ? count($cols) : 0;
        $rows = $data['rows'] ?? [];
        $rowCount = is_array($rows) ? count($rows) : 0;

        $notes = [];
        if ($rowCount > 0 && $colCount > 1) {
            $notes[] = 'Таблица на сайте сохраняет колонки; ширина и прокрутка задаются темой.';
        }

        return [
            'На сайте: таблица данных в разметке темы'.($colCount > 0 ? ' ('.$colCount.' кол.)' : '').'.',
            $notes,
            [
                'render_mode' => 'data_table',
                'width_mode' => 'readable',
                'theme_variant' => $themeKey.'_table',
            ],
        ];
    }

    /**
     * @return array{0: string, 1: list<string>, 2: array<string, string>}
     */
    private function richTextPresentation(string $themeKey, string $pageMode): array
    {
        return [
            'На сайте: свободный HTML/текст в потоке страницы (стили темы).',
            $pageMode === 'home'
                ? ['На главной блоки выводятся в порядке списка ниже.']
                : [],
            [
                'render_mode' => 'rich_html',
                'theme_variant' => $themeKey.'_rich',
            ],
        ];
    }
}
