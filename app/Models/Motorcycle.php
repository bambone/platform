<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
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
        'cover_image',
        'short_description',
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
        $this->mediaCollections[] = MediaCollection::create('cover')->singleFile();
        $this->mediaCollections[] = MediaCollection::create('gallery');
    }

    public function getCoverUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('cover');
        if ($media) {
            return $media->getUrl();
        }
        if ($this->cover_image) {
            $path = ltrim((string) $this->cover_image, '/');
            if (str_starts_with($path, 'motolevins/')) {
                return asset('images/'.$path);
            }
            if (str_starts_with($path, 'bikes/')) {
                return asset('images/motolevins/'.$path);
            }
            if (str_starts_with($path, 'images/')) {
                return asset($path);
            }

            return asset('storage/'.$path);
        }

        return null;
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
}
