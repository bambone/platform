<?php

namespace Tests\Support;

/**
 * Deterministic markers: no marker is a substring of another (pairwise), besides shared prefix is OK for strpos order if we always search full strings.
 * We use distinct suffix patterns per type.
 */
final class PageBuilderSmokeFixtures
{
    public const MARKER_MAIN = 'PB_SMOKE_MAIN_PRIM_ZW9';

    public const MARKER_STRUCTURED_TEXT = 'PB_SMOKE_A7F2E01_STX';

    public const MARKER_TEXT_SECTION = 'PB_SMOKE_B8G3K02_TXX';

    public const MARKER_CONTENT_FAQ = 'PB_SMOKE_C9H4L03_CFX';

    public const MARKER_LIST_BLOCK = 'PB_SMOKE_D0J5M04_LBX';

    public const MARKER_INFO_CARDS = 'PB_SMOKE_E1K6N05_ICX';

    public const MARKER_CONTACTS_INFO = 'PB_SMOKE_F2L7P06_CIX';

    public const MARKER_DATA_TABLE = 'PB_SMOKE_G3M8Q07_DTX';

    public const MARKER_NOTICE_BOX = 'PB_SMOKE_H4N9R08_NBX';

    public const MARKER_HIDDEN = 'PB_SMOKE_NEVER_SHOW_HIDDEN_Q1';

    public const MARKER_DRAFT = 'PB_SMOKE_NEVER_SHOW_DRAFT_Q2';

    /** Published visible control block on visibility test page. */
    public const MARKER_PUBLISHED_VISIBLE = 'PB_SMOKE_OK_PUBLISHED_BLOCK_ZZ';

    public const PAGE_TITLE_RICH = 'Smoke Rich Page QM8K3';

    public const SLUG_RICH = 'smoke-rich-qm8k3';

    public const PAGE_TITLE_EDGE = 'Smoke Edge Page LP2N7';

    public const SLUG_EDGE = 'smoke-edge-lp2n7';

    public const PAGE_TITLE_VISIBILITY = 'Smoke Visibility JV4R9';

    public const SLUG_VISIBILITY = 'smoke-visibility-jv4r9';

    public const MARKER_HOME_HERO = 'PB_SMOKE_HOME_HERO_LIVE';

    /** @var list<string> */
    public const ORDERED_CONTENT_TYPES = [
        'structured_text',
        'text_section',
        'content_faq',
        'list_block',
        'info_cards',
        'contacts_info',
        'data_table',
        'notice_box',
    ];

    /** @return list<string> */
    public static function richExtraMarkersInOrder(): array
    {
        return [
            self::MARKER_STRUCTURED_TEXT,
            self::MARKER_TEXT_SECTION,
            self::MARKER_CONTENT_FAQ,
            self::MARKER_LIST_BLOCK,
            self::MARKER_INFO_CARDS,
            self::MARKER_CONTACTS_INFO,
            self::MARKER_DATA_TABLE,
            self::MARKER_NOTICE_BOX,
        ];
    }

    /**
     * @return array<string, string> type_id => marker
     */
    public static function markerByType(): array
    {
        return [
            'structured_text' => self::MARKER_STRUCTURED_TEXT,
            'text_section' => self::MARKER_TEXT_SECTION,
            'content_faq' => self::MARKER_CONTENT_FAQ,
            'list_block' => self::MARKER_LIST_BLOCK,
            'info_cards' => self::MARKER_INFO_CARDS,
            'contacts_info' => self::MARKER_CONTACTS_INFO,
            'data_table' => self::MARKER_DATA_TABLE,
            'notice_box' => self::MARKER_NOTICE_BOX,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mainSectionAttributes(int $sortOrder = 0): array
    {
        return [
            'section_key' => 'main',
            'section_type' => 'rich_text',
            'title' => 'Основной текст',
            'data_json' => [
                'content' => '<p>'.self::MARKER_MAIN.'</p><p>Intro line for smoke.</p>',
            ],
            'sort_order' => $sortOrder,
            'status' => 'published',
            'is_visible' => true,
        ];
    }

    /**
     * All eight extras with sort_order 10..80.
     *
     * @return list<array<string, mixed>>
     */
    public static function richExtraSectionRows(): array
    {
        return [
            [
                'section_key' => 'structured_text_10',
                'section_type' => 'structured_text',
                'title' => 'Rich ST',
                'data_json' => [
                    'title' => 'Structured title',
                    'content' => '<p>'.self::MARKER_STRUCTURED_TEXT.'</p><ul><li><a href="https://example.test/smoke-rich">Smoke link</a></li></ul>',
                    'max_width' => 'prose',
                ],
                'sort_order' => 10,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'text_section_20',
                'section_type' => 'text_section',
                'title' => 'Rich TX',
                'data_json' => [
                    'title' => 'Text section title',
                    'content' => '<p>'.self::MARKER_TEXT_SECTION.'</p><h3>Sub</h3>',
                ],
                'sort_order' => 20,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'content_faq_30',
                'section_type' => 'content_faq',
                'title' => 'Rich FAQ',
                'data_json' => [
                    'title' => 'FAQ block',
                    'items' => [
                        ['question' => self::MARKER_CONTENT_FAQ.' Q1', 'answer' => '<p>Answer one</p>'],
                        ['question' => 'Second question', 'answer' => '<p>Second answer</p>'],
                    ],
                ],
                'sort_order' => 30,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'list_block_40',
                'section_type' => 'list_block',
                'title' => 'Rich LB',
                'data_json' => [
                    'title' => 'Steps',
                    'variant' => 'steps',
                    'items' => [
                        ['title' => 'Step A', 'text' => self::MARKER_LIST_BLOCK],
                        ['title' => 'Step B', 'text' => 'Follow-up'],
                    ],
                ],
                'sort_order' => 40,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'info_cards_50',
                'section_type' => 'info_cards',
                'title' => 'Rich IC',
                'data_json' => [
                    'title' => 'Cards',
                    'columns' => 2,
                    'items' => [
                        ['icon' => 'check', 'title' => self::MARKER_INFO_CARDS, 'text' => 'Card A body'],
                        ['icon' => 'info', 'title' => 'Other', 'text' => 'Card B body'],
                    ],
                ],
                'sort_order' => 50,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'contacts_info_60',
                'section_type' => 'contacts_info',
                'title' => 'Rich CI',
                'data_json' => [
                    'title' => 'Reach us',
                    'description' => null,
                    'phone' => '+79991112299',
                    'email' => null,
                    'whatsapp' => null,
                    'telegram' => null,
                    'address' => 'Smoke Addr '.self::MARKER_CONTACTS_INFO,
                    'working_hours' => null,
                    'map_embed' => null,
                    'map_link' => null,
                ],
                'sort_order' => 60,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'data_table_70',
                'section_type' => 'data_table',
                'title' => 'Rich DT',
                'data_json' => [
                    'title' => 'Tariffs',
                    'columns' => [
                        ['name' => 'Col A'],
                        ['name' => 'Col B'],
                    ],
                    'rows' => [
                        ['cells' => [['value' => self::MARKER_DATA_TABLE], ['value' => 'Val 2']]],
                        ['cells' => [['value' => 'R2C1'], ['value' => 'R2C2']]],
                    ],
                ],
                'sort_order' => 70,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'notice_box_80',
                'section_type' => 'notice_box',
                'title' => 'Rich NB',
                'data_json' => [
                    'title' => null,
                    'text' => '<p>'.self::MARKER_NOTICE_BOX.'</p>',
                    'tone' => 'warning',
                ],
                'sort_order' => 80,
                'status' => 'published',
                'is_visible' => true,
            ],
        ];
    }

    /**
     * One extra row for matrix "single block" tests.
     *
     * @return array<string, mixed>
     */
    public static function singleExtraRowForType(string $typeId): array
    {
        $rows = self::richExtraSectionRows();
        foreach ($rows as $row) {
            if (($row['section_type'] ?? '') === $typeId) {
                $copy = $row;
                $copy['section_key'] = $typeId.'_solo_10';
                $copy['sort_order'] = 10;

                return $copy;
            }
        }

        throw new \InvalidArgumentException('Unknown content type: '.$typeId);
    }

    /**
     * Sparse optional fields; each type still emits its marker.
     *
     * @return list<array<string, mixed>>
     */
    public static function edgeExtraSectionRows(): array
    {
        return [
            [
                'section_key' => 'structured_text_edge_10',
                'section_type' => 'structured_text',
                'title' => 'Edge ST',
                'data_json' => [
                    'title' => null,
                    'content' => '<p>'.self::MARKER_STRUCTURED_TEXT.'-EDGE</p>',
                    'max_width' => 'full',
                ],
                'sort_order' => 10,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'text_section_edge_20',
                'section_type' => 'text_section',
                'title' => 'Edge TX',
                'data_json' => [
                    'title' => '',
                    'content' => '<p>'.self::MARKER_TEXT_SECTION.'-EDGE</p>',
                ],
                'sort_order' => 20,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'content_faq_edge_30',
                'section_type' => 'content_faq',
                'title' => 'Edge FAQ',
                'data_json' => [
                    'title' => '',
                    'items' => [
                        ['question' => self::MARKER_CONTENT_FAQ.'-EDGE', 'answer' => '<p>Only</p>'],
                    ],
                ],
                'sort_order' => 30,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'list_block_edge_40',
                'section_type' => 'list_block',
                'title' => 'Edge LB',
                'data_json' => [
                    'title' => null,
                    'variant' => 'bullets',
                    'items' => [
                        ['title' => '', 'text' => self::MARKER_LIST_BLOCK.'-EDGE'],
                    ],
                ],
                'sort_order' => 40,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'info_cards_edge_50',
                'section_type' => 'info_cards',
                'title' => 'Edge IC',
                'data_json' => [
                    'title' => null,
                    'columns' => 3,
                    'items' => [
                        ['icon' => 'star', 'title' => 'T', 'text' => self::MARKER_INFO_CARDS.'-EDGE'],
                    ],
                ],
                'sort_order' => 50,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'contacts_info_edge_60',
                'section_type' => 'contacts_info',
                'title' => 'Edge CI',
                'data_json' => [
                    'title' => 'Контакты',
                    'description' => '',
                    'phone' => '+79990003388',
                    'email' => null,
                    'whatsapp' => null,
                    'telegram' => null,
                    'address' => self::MARKER_CONTACTS_INFO.'-EDGE',
                    'working_hours' => null,
                    'map_embed' => null,
                    'map_link' => null,
                ],
                'sort_order' => 60,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'data_table_edge_70',
                'section_type' => 'data_table',
                'title' => 'Edge DT',
                'data_json' => [
                    'title' => null,
                    'columns' => [['name' => 'C1']],
                    'rows' => [
                        ['cells' => [['value' => self::MARKER_DATA_TABLE.'-EDGE']]],
                    ],
                ],
                'sort_order' => 70,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'notice_box_edge_80',
                'section_type' => 'notice_box',
                'title' => 'Edge NB',
                'data_json' => [
                    'title' => null,
                    'text' => '<p>'.self::MARKER_NOTICE_BOX.'-EDGE</p>',
                    'tone' => 'info',
                ],
                'sort_order' => 80,
                'status' => 'published',
                'is_visible' => true,
            ],
        ];
    }

    /**
     * Hidden + draft rows for visibility page (markers must not appear publicly).
     *
     * @return list<array<string, mixed>>
     */
    public static function publishedVisibleExtraRow(): array
    {
        return [
            'section_key' => 'structured_text_visible_ok',
            'section_type' => 'structured_text',
            'title' => 'OK',
            'data_json' => [
                'title' => null,
                'content' => '<p>'.self::MARKER_PUBLISHED_VISIBLE.'</p>',
                'max_width' => 'prose',
            ],
            'sort_order' => 10,
            'status' => 'published',
            'is_visible' => true,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function hiddenAndDraftRows(): array
    {
        return [
            [
                'section_key' => 'structured_text_hidden',
                'section_type' => 'structured_text',
                'title' => 'Hidden',
                'data_json' => [
                    'title' => null,
                    'content' => '<p>'.self::MARKER_HIDDEN.'</p>',
                    'max_width' => 'prose',
                ],
                'sort_order' => 100,
                'status' => 'published',
                'is_visible' => false,
            ],
            [
                'section_key' => 'text_section_draft',
                'section_type' => 'text_section',
                'title' => 'Draft',
                'data_json' => [
                    'title' => 'X',
                    'content' => '<p>'.self::MARKER_DRAFT.'</p>',
                ],
                'sort_order' => 110,
                'status' => 'draft',
                'is_visible' => true,
            ],
        ];
    }

    /**
     * Minimal home sections: keys expected by home.blade / components.
     *
     * @return list<array<string, mixed>>
     */
    public static function homeSectionRows(): array
    {
        return [
            [
                'section_key' => 'hero',
                'section_type' => 'hero',
                'title' => 'Hero',
                'data_json' => [
                    'heading' => self::MARKER_HOME_HERO.' Аренда',
                    'subheading' => 'smoke sub',
                    'description' => 'Smoke hero description line.',
                    'video_poster' => '',
                    'video_src' => '',
                ],
                'sort_order' => 0,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'route_cards',
                'section_type' => 'features',
                'title' => 'Routes',
                'data_json' => [
                    'items' => [
                        ['title' => 'A', 'description' => 'd1', 'icon' => 'coast'],
                    ],
                ],
                'sort_order' => 10,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'motorcycle_catalog',
                'section_type' => 'motorcycle_catalog',
                'title' => 'Каталог',
                'data_json' => [
                    'heading' => 'Каталог (smoke)',
                    'subheading' => 'Подзаголовок каталога для теста.',
                ],
                'sort_order' => 25,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'why_us',
                'section_type' => 'features',
                'title' => 'Why',
                'data_json' => [
                    'heading' => 'Why us',
                    'lead' => 'Lead',
                    'items' => [
                        ['title' => 'One', 'description' => 'D1'],
                    ],
                ],
                'sort_order' => 30,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'how_it_works',
                'section_type' => 'features',
                'title' => 'How',
                'data_json' => [
                    'lead' => 'L',
                    'items' => [
                        ['step' => 1, 'title' => 'S1', 'description' => 'D'],
                    ],
                ],
                'sort_order' => 40,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'rental_conditions',
                'section_type' => 'features',
                'title' => 'Cond',
                'data_json' => [
                    'items' => [
                        ['title' => 'T', 'description' => 'D', 'badge' => '21+'],
                    ],
                ],
                'sort_order' => 50,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'reviews_block',
                'section_type' => 'cards_teaser',
                'title' => 'Reviews',
                'data_json' => [
                    'show_block' => true,
                    'selected_review_ids' => [],
                    'heading' => 'Reviews',
                    'subheading' => 'Sub',
                ],
                'sort_order' => 60,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'faq_block',
                'section_type' => 'faq',
                'title' => 'FAQ',
                'data_json' => [
                    'show_on_home' => true,
                    'heading' => 'FAQ H',
                    'subheading' => 'FAQ S',
                ],
                'sort_order' => 70,
                'status' => 'published',
                'is_visible' => true,
            ],
            [
                'section_key' => 'final_cta',
                'section_type' => 'cta',
                'title' => 'CTA',
                'data_json' => [
                    'heading' => 'CTA heading',
                    'description' => 'CTA body',
                    'button_text' => 'Go',
                ],
                'sort_order' => 80,
                'status' => 'published',
                'is_visible' => true,
            ],
        ];
    }

    /**
     * Preset: full rich public page (slug/title + main + eight extras).
     *
     * @return array{slug: string, page_title: string, main: array<string, mixed>, extras: list<array<string, mixed>>}
     */
    public static function richContentFixture(): array
    {
        return [
            'slug' => self::SLUG_RICH,
            'page_title' => self::PAGE_TITLE_RICH,
            'main' => self::mainSectionAttributes(),
            'extras' => self::richExtraSectionRows(),
        ];
    }

    /**
     * Preset: legal-style page — main + single notice (warning).
     *
     * @return array{slug: string, page_title: string, main: array<string, mixed>, extras: list<array<string, mixed>>}
     */
    public static function legalPageFixture(): array
    {
        return [
            'slug' => 'smoke-legal-preset',
            'page_title' => 'Smoke legal preset',
            'main' => self::mainSectionAttributes(),
            'extras' => [[
                'section_key' => 'notice_box_legal',
                'section_type' => 'notice_box',
                'title' => 'Legal notice',
                'data_json' => [
                    'title' => 'Условие',
                    'text' => '<p>PB_PRESET_LEGAL_MARKER_7H3Q</p>',
                    'tone' => 'warning',
                ],
                'sort_order' => 10,
                'status' => 'published',
                'is_visible' => true,
            ]],
        ];
    }

    /**
     * Preset: main + FAQ with several items (heavy repeater).
     *
     * @return array{slug: string, page_title: string, main: array<string, mixed>, extras: list<array<string, mixed>>}
     */
    public static function faqHeavyFixture(): array
    {
        $items = [];
        for ($i = 1; $i <= 6; $i++) {
            $items[] = [
                'question' => 'PB_PRESET_FAQ_Q_'.$i.'_X4M',
                'answer' => '<p>Answer '.$i.'</p>',
            ];
        }

        return [
            'slug' => 'smoke-faq-heavy-preset',
            'page_title' => 'Smoke FAQ heavy',
            'main' => self::mainSectionAttributes(),
            'extras' => [[
                'section_key' => 'content_faq_heavy',
                'section_type' => 'content_faq',
                'title' => 'FAQ',
                'data_json' => [
                    'title' => 'Частые вопросы',
                    'items' => $items,
                ],
                'sort_order' => 10,
                'status' => 'published',
                'is_visible' => true,
            ]],
        ];
    }

    /**
     * Preset: main + contacts_info phone only (no map).
     *
     * @return array{slug: string, page_title: string, main: array<string, mixed>, extras: list<array<string, mixed>>}
     */
    public static function contactsMinimalFixture(): array
    {
        return [
            'slug' => 'smoke-contacts-min-preset',
            'page_title' => 'Smoke contacts min',
            'main' => self::mainSectionAttributes(),
            'extras' => [[
                'section_key' => 'contacts_info_min',
                'section_type' => 'contacts_info',
                'title' => 'Контакты',
                'data_json' => [
                    'title' => 'Контакты',
                    'description' => null,
                    'phone' => '+79995554433',
                    'email' => null,
                    'whatsapp' => null,
                    'telegram' => null,
                    'address' => null,
                    'working_hours' => null,
                    'map_embed' => null,
                    'map_link' => null,
                ],
                'sort_order' => 10,
                'status' => 'published',
                'is_visible' => true,
            ]],
        ];
    }
}
