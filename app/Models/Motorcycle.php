<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\CatalogHighlightNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;

class Motorcycle extends Model implements HasMedia
{
    use BelongsToTenant, HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'brand',
        'model',
        'category_id',
        'short_description',
        'catalog_scenario',
        'catalog_highlight_1',
        'catalog_highlight_2',
        'catalog_highlight_3',
        'catalog_price_note',
        'detail_audience',
        'detail_use_case_bullets',
        'detail_advantage_bullets',
        'detail_rental_notes',
        'full_description',
        'price_per_day',
        'price_2_3_days',
        'price_week',
        'status',
        'engine_cc',
        'power',
        'transmission',
        'year',
        'mileage',
        'specs_json',
        'tags_json',
        'sort_order',
        'show_on_home',
        'show_in_catalog',
        'is_recommended',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Motorcycle $motorcycle) {
            if (empty($motorcycle->slug) && ! empty($motorcycle->name)) {
                $base = Str::slug($motorcycle->name);
                $slug = $base;
                $i = 1;
                $q = static::withoutGlobalScopes()->where('slug', $slug);
                if (! empty($motorcycle->tenant_id)) {
                    $q->where('tenant_id', $motorcycle->tenant_id);
                }
                while ($q->exists()) {
                    $slug = $base.'-'.$i++;
                    $q = static::withoutGlobalScopes()->where('slug', $slug);
                    if (! empty($motorcycle->tenant_id)) {
                        $q->where('tenant_id', $motorcycle->tenant_id);
                    }
                }
                $motorcycle->slug = $slug;
            }
        });
    }

    protected $casts = [
        'price_per_day' => 'integer',
        'price_2_3_days' => 'integer',
        'price_week' => 'integer',
        'engine_cc' => 'integer',
        'power' => 'integer',
        'year' => 'integer',
        'mileage' => 'integer',
        'specs_json' => 'array',
        'tags_json' => 'array',
        'detail_use_case_bullets' => 'array',
        'detail_advantage_bullets' => 'array',
        'show_on_home' => 'boolean',
        'show_in_catalog' => 'boolean',
        'is_recommended' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    public function rentalUnits(): HasMany
    {
        return $this->hasMany(RentalUnit::class);
    }

    public function registerMediaCollections(): void
    {
        $disk = (string) config('media-library.disk_name', 'public');
        $this->mediaCollections[] = MediaCollection::create('cover')
            ->useDisk($disk)
            ->storeConversionsOnDisk($disk)
            ->singleFile();
        $this->mediaCollections[] = MediaCollection::create('gallery')
            ->useDisk($disk)
            ->storeConversionsOnDisk($disk);
    }

    public function getCoverUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('cover');

        return $media ? $media->getUrl() : null;
    }

    public static function statuses(): array
    {
        return [
            'available' => 'Доступен',
            'hidden' => 'Скрыт',
            'maintenance' => 'В обслуживании',
            'booked' => 'Забронирован',
            'archived' => 'В архиве',
        ];
    }

    /**
     * Контент карточки каталога: поля БД, при пустых — мягкий fallback по slug категории из config,
     * до 3 чипов; при нехватке — один чип из поля transmission, если оно явно указывает на автомат/вариатор.
     *
     * @return array{positioning: string, scenario: string, highlights: array<int, string>, price_note: string}
     */
    public function catalogCardForView(): array
    {
        $slug = $this->category?->slug;

        $defaults = $slug
            ? (array) config('tenant_landing.catalog_card_defaults_by_category_slug.'.$slug, [])
            : [];

        $positioning = trim((string) ($this->short_description ?? ''));
        if ($positioning === '' && isset($defaults['positioning'])) {
            $positioning = (string) $defaults['positioning'];
        }

        $scenario = trim((string) ($this->catalog_scenario ?? ''));
        if ($scenario === '' && isset($defaults['scenario'])) {
            $scenario = (string) $defaults['scenario'];
        }

        $highlights = array_values(array_filter([
            $this->catalog_highlight_1,
            $this->catalog_highlight_2,
            $this->catalog_highlight_3,
        ], fn ($v) => filled($v)));

        if ($highlights === [] && isset($defaults['highlights']) && is_array($defaults['highlights'])) {
            $highlights = array_values(array_filter($defaults['highlights'], fn ($v) => filled($v)));
        }

        $highlights = array_slice($highlights, 0, 3);

        if (count($highlights) < 3 && filled($this->transmission)) {
            $t = mb_strtolower((string) $this->transmission);
            $chipKey = null;
            if (str_contains($t, 'dct') || str_contains($t, 'автомат')) {
                $chipKey = 'automatic';
            } elseif (str_contains($t, 'вариатор')) {
                $chipKey = 'variator';
            }
            if ($chipKey !== null && ! in_array($chipKey, $highlights, true)) {
                $highlights[] = $chipKey;
                $highlights = array_slice($highlights, 0, 3);
            }
        }

        $highlights = CatalogHighlightNormalizer::normalizeToLabels($highlights);

        $priceNote = trim((string) ($this->catalog_price_note ?? ''));

        return [
            'positioning' => $positioning,
            'scenario' => $scenario,
            'highlights' => $highlights,
            'price_note' => $priceNote,
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public function specRowsForPublic(): array
    {
        $rows = [];
        if (filled($this->engine_cc)) {
            $rows[] = ['Объём двигателя', number_format((int) $this->engine_cc, 0, ',', ' ').' см³'];
        }
        if (filled($this->power)) {
            $rows[] = ['Мощность', (string) $this->power.' л.с.'];
        }
        if (filled($this->transmission)) {
            $rows[] = ['Трансмиссия', (string) $this->transmission];
        }
        if (filled($this->year)) {
            $rows[] = ['Год выпуска', (string) $this->year];
        }
        if (filled($this->mileage)) {
            $rows[] = ['Пробег', number_format((int) $this->mileage, 0, ',', ' ').' км'];
        }
        if ($this->category) {
            $rows[] = ['Класс', (string) $this->category->name];
        }

        $specs = $this->specs_json ?? [];
        if (is_array($specs)) {
            $n = 0;
            foreach ($specs as $label => $value) {
                if (! is_string($label) || $label === '' || $value === null || $value === '') {
                    continue;
                }
                $rows[] = [$label, is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)];
                if (++$n >= 8) {
                    break;
                }
            }
        }

        return $rows;
    }

    /**
     * Характеристики для страницы модели: сгруппированы по смыслу (без плоского «двух строк»).
     *
     * @return array<string, array<int, array{0: string, 1: string}>>
     */
    public function specGroupsForPublic(): array
    {
        $motor = [];
        if (filled($this->engine_cc)) {
            $motor[] = ['Объём', number_format((int) $this->engine_cc, 0, ',', ' ').' см³'];
        }
        if (filled($this->power)) {
            $motor[] = ['Мощность', (string) $this->power.' л.с.'];
        }
        if (filled($this->transmission)) {
            $motor[] = ['Трансмиссия', (string) $this->transmission];
        }

        $identity = [];
        if ($this->category) {
            $identity[] = ['Класс', (string) $this->category->name];
        }
        if (filled($this->year)) {
            $identity[] = ['Год', (string) $this->year];
        }
        if (filled($this->mileage)) {
            $identity[] = ['Пробег', number_format((int) $this->mileage, 0, ',', ' ').' км'];
        }

        $extra = [];
        $specs = $this->specs_json ?? [];
        if (is_array($specs)) {
            $n = 0;
            foreach ($specs as $label => $value) {
                if (! is_string($label) || $label === '' || $value === null || $value === '') {
                    continue;
                }
                $extra[] = [$label, is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)];
                if (++$n >= 8) {
                    break;
                }
            }
        }

        $groups = [];
        if ($motor !== []) {
            $groups['Мотор и передача'] = $motor;
        }
        if ($identity !== []) {
            $groups['Класс и состояние'] = $identity;
        }
        if ($extra !== []) {
            $groups['Оснащение и параметры'] = $extra;
        }

        return $groups;
    }

    /**
     * @return array{audience: string, use_case: array<int, string>, advantages: array<int, string>, rental_notes: string}
     */
    public function detailContentForView(): array
    {
        $slug = $this->category?->slug;
        $defaults = $slug
            ? (array) config('tenant_landing.catalog_card_defaults_by_category_slug.'.$slug, [])
            : [];

        $audience = trim((string) ($this->detail_audience ?? ''));
        if ($audience === '') {
            $scenario = trim((string) ($this->catalog_scenario ?? ''));
            if ($scenario === '' && isset($defaults['scenario'])) {
                $scenario = (string) $defaults['scenario'];
            }
            if ($scenario !== '') {
                $audience = 'Подойдёт тем, кто выбирает сценарий: '.$scenario.'.';
            }
        }

        $useCase = array_values(array_filter(array_slice($this->detail_use_case_bullets ?? [], 0, 4), 'filled'));
        if ($useCase === [] && isset($defaults['detail_use_case']) && is_array($defaults['detail_use_case'])) {
            $useCase = array_values(array_filter(array_slice($defaults['detail_use_case'], 0, 4), 'filled'));
        }

        $advantages = array_values(array_filter(array_slice($this->detail_advantage_bullets ?? [], 0, 6), 'filled'));
        if ($advantages === []) {
            $advantages = CatalogHighlightNormalizer::normalizeToLabels([
                $this->catalog_highlight_1,
                $this->catalog_highlight_2,
                $this->catalog_highlight_3,
            ]);
        }

        $rentalNotes = trim((string) ($this->detail_rental_notes ?? ''));

        return [
            'audience' => $audience,
            'use_case' => $useCase,
            'advantages' => $advantages,
            'rental_notes' => $rentalNotes,
        ];
    }
}
