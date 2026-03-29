<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;

class Review extends Model implements HasMedia
{
    use BelongsToTenant, InteractsWithMedia;

    protected $fillable = [
        'tenant_id',
        'name',
        'city',
        'text',
        'rating',
        'avatar',
        'photos_json',
        'motorcycle_id',
        'date',
        'source',
        'status',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'photos_json' => 'array',
        'date' => 'date',
        'is_featured' => 'boolean',
    ];

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function registerMediaCollections(): void
    {
        $this->mediaCollections[] = MediaCollection::create('avatar')->singleFile();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('avatar');
        if ($media) {
            return $media->getUrl();
        }
        if ($this->avatar) {
            $path = ltrim((string) $this->avatar, '/');
            if (str_starts_with($path, 'images/')) {
                return asset($path);
            }
            if (str_starts_with($path, 'motolevins/')) {
                return asset('images/'.$path);
            }

            return asset('storage/'.$path);
        }

        return null;
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
