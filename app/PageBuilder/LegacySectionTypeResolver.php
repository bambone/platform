<?php

namespace App\PageBuilder;

use App\Models\PageSection;

/**
 * Read-time mapping for legacy rows (missing section_type or moto-specific keys).
 */
final class LegacySectionTypeResolver
{
    /**
     * Legacy section_key → effective blueprint id for rendering / admin display.
     *
     * @var array<string, string>
     */
    private const KEY_TO_TYPE = [
        'hero' => 'hero',
        'main' => 'rich_text',
        'route_cards' => 'features',
        'fleet_block' => 'cards_teaser',
        'why_us' => 'features',
        'how_it_works' => 'features',
        'rental_conditions' => 'features',
        'reviews_block' => 'cards_teaser',
        'faq_block' => 'faq',
        'final_cta' => 'cta',
        'motorcycle_catalog' => 'motorcycle_catalog',
    ];

    public function __construct(
        private readonly PageSectionTypeRegistry $registry
    ) {}

    /**
     * Effective type id for a DB row (never null for known keys; falls back to rich_text).
     */
    public function effectiveTypeId(PageSection $section): string
    {
        $type = $section->section_type;
        if (is_string($type) && $type !== '' && $this->registry->has($type)) {
            return $type;
        }

        $key = (string) $section->section_key;
        if (isset(self::KEY_TO_TYPE[$key])) {
            return self::KEY_TO_TYPE[$key];
        }

        if ($type === 'html' || $type === '') {
            return 'rich_text';
        }

        return 'rich_text';
    }
}
