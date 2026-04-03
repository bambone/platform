<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSection extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::updating(function (PageSection $section): void {
            if (! $section->isDirty('data_json')) {
                return;
            }
            $incoming = $section->data_json;
            if (! is_array($incoming)) {
                return;
            }
            $type = $section->section_type;
            $isMain = $section->section_key === 'main';
            $legacyHtml = $type === null || $type === '' || $type === 'html';
            if (! $isMain && ! $legacyHtml) {
                return;
            }
            $original = $section->getOriginal('data_json');
            if (! is_array($original) || $original === []) {
                return;
            }
            $section->data_json = array_merge($original, $incoming);
        });
    }

    protected $fillable = [
        'tenant_id',
        'page_id',
        'section_key',
        'section_type',
        'title',
        'data_json',
        'sort_order',
        'is_visible',
        'status',
    ];

    protected $casts = [
        'data_json' => 'array',
        'is_visible' => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public static function sectionKeys(): array
    {
        return [
            'hero' => 'Hero (главный баннер)',
            'route_cards' => 'Карточки маршрутов',
            'fleet_block' => 'Блок автопарка',
            'why_us' => 'Почему мы',
            'how_it_works' => 'Как это работает',
            'rental_conditions' => 'Условия аренды',
            'reviews_block' => 'Блок отзывов',
            'faq_block' => 'Блок FAQ',
            'final_cta' => 'Финальный CTA',
            'motorcycle_catalog' => 'Каталог мотоциклов (главная)',
        ];
    }

    public static function statuses(): array
    {
        return [
            'draft' => 'Черновик',
            'published' => 'Опубликован',
            'hidden' => 'Скрыт',
        ];
    }
}
