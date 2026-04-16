<?php

use App\Models\PageSection;
use App\PageBuilder\PageSectionTypeRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Заполняет пустые `page_sections.title` («Подпись в списке») для всех тенантов.
 * В админке поле обязательное; в БД исторически допускался NULL/пустая строка.
 *
 * Имя берётся из подписи типа блока (blueprint label), как при создании секции в UI.
 * Повторы одного и того же названия на одной странице различаются суффиксом (2), (3), …
 */
return new class extends Migration
{
    /**
     * Совпадает с {@see \App\PageBuilder\LegacySectionTypeResolver::KEY_TO_TYPE}.
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

    public function up(): void
    {
        /** @var PageSectionTypeRegistry $registry */
        $registry = app(PageSectionTypeRegistry::class);

        $pageIds = DB::table('page_sections')
            ->where(function ($q): void {
                $q->whereNull('title')->orWhere('title', '');
            })
            ->distinct()
            ->orderBy('page_id')
            ->pluck('page_id');

        foreach ($pageIds as $pageId) {
            $rows = DB::table('page_sections')
                ->where('page_id', $pageId)
                ->where(function ($q): void {
                    $q->whereNull('title')->orWhere('title', '');
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'page_id', 'section_key', 'section_type', 'sort_order']);

            $baseUsage = [];

            foreach ($rows as $row) {
                $effectiveType = $this->effectiveTypeId($row, $registry);
                $base = $registry->has($effectiveType)
                    ? trim((string) $registry->get($effectiveType)->label())
                    : '';
                if ($base === '') {
                    $base = $this->fallbackLabelFromSectionKey((string) $row->section_key, (int) $row->id);
                }
                $base = $this->truncateForTitle($base, 240);

                $usageKey = $base;
                $next = ($baseUsage[$usageKey] ?? 0) + 1;
                $baseUsage[$usageKey] = $next;

                $title = $next === 1 ? $base : $base.' ('.$next.')';
                $title = $this->truncateForTitle($title, 255);

                DB::table('page_sections')->where('id', $row->id)->update([
                    'title' => $title,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Необратимо: нет маркера «заполнено миграцией».
    }

    private function effectiveTypeId(object $row, PageSectionTypeRegistry $registry): string
    {
        $type = $row->section_type;
        if (is_string($type) && $type !== '' && $registry->has($type)) {
            return $type;
        }

        $key = (string) $row->section_key;
        if (isset(self::KEY_TO_TYPE[$key])) {
            return self::KEY_TO_TYPE[$key];
        }

        if ($type === 'html' || $type === '' || $type === null) {
            return 'rich_text';
        }

        return $registry->has((string) $type) ? (string) $type : 'rich_text';
    }

    private function fallbackLabelFromSectionKey(string $sectionKey, int $id): string
    {
        $map = PageSection::sectionKeys();
        if ($sectionKey !== '' && isset($map[$sectionKey])) {
            return (string) $map[$sectionKey];
        }

        if ($sectionKey !== '') {
            return str_replace('_', ' ', $sectionKey);
        }

        return 'Секция #'.$id;
    }

    private function truncateForTitle(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        if ($maxLength < 2) {
            return mb_substr($value, 0, $maxLength);
        }

        return mb_substr($value, 0, $maxLength - 1).'…';
    }
};
