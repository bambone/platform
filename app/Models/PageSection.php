<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSection extends Model
{
    use BelongsToTenant;

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
