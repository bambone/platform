<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        'category_key',
        'headline',
        'text_short',
        'text_long',
        'media_type',
        'video_url',
        'meta_json',
    ];

    protected $casts = [
        'photos_json' => 'array',
        'meta_json' => 'array',
        'date' => 'date',
        'is_featured' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Review $review): void {
            $long = trim((string) ($review->text_long ?? ''));
            $short = trim((string) ($review->text_short ?? ''));
            if ($short === '' && $long !== '') {
                $plain = trim(preg_replace('/\s+/', ' ', strip_tags($long)) ?? '');
                $review->text_short = Str::limit($plain, 240, '…');
            }
            $legacy = trim((string) ($review->text ?? ''));
            if ($legacy === '') {
                $review->text = $long !== '' ? $long : (string) ($review->text_short ?? '');
            }
        });
    }

    public function getDisplayBodyAttribute(): string
    {
        $long = trim((string) ($this->text_long ?? ''));
        if ($long !== '') {
            return $long;
        }
        $short = trim((string) ($this->text_short ?? ''));

        return $short !== '' ? $short : (string) ($this->text ?? '');
    }

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function registerMediaCollections(): void
    {
        $disk = (string) config('media-library.disk_name', 'public');
        $this->mediaCollections[] = MediaCollection::create('avatar')
            ->useDisk($disk)
            ->storeConversionsOnDisk($disk)
            ->singleFile();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('avatar');
        if ($media) {
            return $media->getUrl();
        }
        if ($this->avatar) {
            $path = ltrim((string) $this->avatar, '/');
            $fromLegacy = theme_platform_url_from_legacy_public_path($path);
            if ($fromLegacy !== null && $fromLegacy !== '') {
                return $fromLegacy;
            }
            if (str_starts_with($path, 'images/')) {
                return asset($path);
            }

            $disk = Storage::disk(TenantStorageDisks::publicDiskName());
            if ($disk instanceof FilesystemAdapter) {
                if (TenantStorageDisks::usesLocalFlyAdapter($disk)) {
                    return $disk->exists($path) ? $disk->url($path) : null;
                }

                return $disk->url($path);
            }

            return null;
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

    /**
     * Featured first, then sort_order, id (review_feed section).
     *
     * @return Collection<int, self>
     */
    public static function forReviewFeed(int $tenantId, array $data): Collection
    {
        $limit = max(1, min(24, (int) ($data['limit'] ?? 9)));
        $category = isset($data['category_key']) ? trim((string) $data['category_key']) : '';
        $categories = $data['category_keys'] ?? null;
        $categoryList = [];
        if (is_array($categories)) {
            foreach ($categories as $c) {
                if (is_string($c) && $c !== '') {
                    $categoryList[] = $c;
                }
            }
            $categoryList = array_values(array_unique($categoryList));
        }

        $base = static::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->when($category !== '', fn ($q) => $q->where('category_key', $category))
            ->when($category === '' && $categoryList !== [], fn ($q) => $q->whereIn('category_key', $categoryList));

        $featured = (clone $base)
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($featured->count() >= $limit) {
            return $featured;
        }

        $rest = (clone $base)
            ->where('is_featured', false)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit - $featured->count())
            ->get();

        return $featured->concat($rest)->unique('id')->values();
    }
}
