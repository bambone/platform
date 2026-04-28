<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Storage\TenantPublicAssetResolver;
use App\Support\Storage\TenantStorageDisks;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;

class Review extends Model implements HasMedia
{
    use BelongsToTenant, InteractsWithMedia;

    /** Лимит символов для текста карточки на публичном сайте (выдержка до «Читать полностью»). */
    public const PUBLIC_CARD_EXCERPT_MAX_CHARS = 220;

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
        'contact_email',
        'submitted_at',
        'moderated_at',
        'moderated_by',
        'moderation_note',
    ];

    protected $casts = [
        'photos_json' => 'array',
        'meta_json' => 'array',
        'date' => 'date',
        'is_featured' => 'boolean',
        'submitted_at' => 'datetime',
        'moderated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Review $review): void {
            $long = trim((string) ($review->text_long ?? ''));
            $legacy = trim((string) ($review->text ?? ''));
            $short = trim((string) ($review->text_short ?? ''));

            // Полный текст: актуальный long → legacy `text` → только потом краткий.
            $full = $long !== '' ? $long : $legacy;

            if ($short === '' && $full !== '') {
                $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($full)) ?? '');
                $review->text_short = Str::limit($plain, self::PUBLIC_CARD_EXCERPT_MAX_CHARS, '…');
                $short = trim((string) $review->text_short);
            }

            // Legacy `text` храним как полную формулировку, чтобы не терять контент после миграций.
            $review->text = $full !== '' ? $full : $short;
        });
    }

    /**
     * Полнота текста без приоритета «краткого»: {@see text_long} → legacy {@see text} → {@see text_short}.
     */
    public function publicFullTextRaw(): string
    {
        $long = trim((string) ($this->text_long ?? ''));
        if ($long !== '') {
            return $long;
        }

        $legacy = trim((string) ($this->text ?? ''));
        if ($legacy !== '') {
            return $legacy;
        }

        return trim((string) ($this->text_short ?? ''));
    }

    public function getDisplayBodyAttribute(): string
    {
        return $this->publicFullTextRaw();
    }

    /**
     * Плоский текст полного отзыва для сайта и сравнения длины карточка/полный текст.
     */
    public function publicBodyPlain(): string
    {
        $raw = $this->publicFullTextRaw();

        return trim(preg_replace('/\s+/u', ' ', strip_tags($raw)) ?? '');
    }

    /**
     * Текст в карточке: явный {@see text_short} или выдержка из полного текста.
     */
    public function publicCardExcerpt(int $maxChars = self::PUBLIC_CARD_EXCERPT_MAX_CHARS): string
    {
        $maxChars = max(32, $maxChars);
        $explicit = trim((string) ($this->text_short ?? ''));

        if ($explicit !== '') {
            $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($explicit)) ?? '');

            return $plain !== '' ? Str::limit($plain, $maxChars, '…') : '';
        }

        $full = $this->publicBodyPlain();

        return $full !== '' ? Str::limit($full, $maxChars, '…') : '';
    }

    /**
     * Нужна ли кнопка «Читать полностью»: полный текст длиннее хранимого краткого или лимита карточки.
     */
    public function publicWantsReadMore(int $maxChars = self::PUBLIC_CARD_EXCERPT_MAX_CHARS): bool
    {
        $maxChars = max(32, $maxChars);
        $full = $this->publicBodyPlain();
        if ($full === '') {
            return false;
        }

        $shortAttr = trim((string) ($this->text_short ?? ''));
        if ($shortAttr !== '') {
            $plainShort = trim(preg_replace('/\s+/u', ' ', strip_tags($shortAttr)) ?? '');
            if ($plainShort === '') {
                return mb_strlen($full) > $maxChars;
            }
            if (mb_strlen($full) > mb_strlen($plainShort)) {
                return true;
            }

            return mb_strlen($plainShort) > $maxChars;
        }

        return mb_strlen($full) > $maxChars;
    }

    /**
     * Короткая подпись источника для публичной витрины: служебные значения (`site`, `import`) не показываем.
     */
    public function publicSourceLabel(): ?string
    {
        $src = trim((string) ($this->source ?? ''));

        return match ($src) {
            'maps_curated' => 'Отзывы с карт',
            'yandex' => 'Яндекс Карты',
            '2gis' => '2ГИС',
            'site', 'import', '' => null,
            default => null,
        };
    }

    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    public function moderatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
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
        $meta = $this->meta_json;
        if (is_array($meta)) {
            $ext = trim((string) ($meta['avatar_external_url'] ?? ''));
            if ($ext !== '' && preg_match('#^https?://#i', $ext) === 1) {
                return $ext;
            }
        }
        if ($this->avatar) {
            $path = ltrim((string) $this->avatar, '/');
            if (preg_match('#^https?://#i', $path) === 1) {
                return $path;
            }
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

    /**
     * Дата для строки «город · дата» на публичной карточке: сначала `date`, иначе `submitted_at`.
     * Каст полей в модели может кинуть при битом значении в БД — тогда смотрим следующее поле. Итог — пустая строка, если ничего нельзя отформатировать.
     */
    public function publicReviewDateFormatted(): string
    {
        foreach (['date', 'submitted_at'] as $key) {
            try {
                $when = $this->getAttribute($key);
            } catch (Throwable) {
                continue;
            }
            if (blank($when)) {
                continue;
            }
            try {
                if ($when instanceof \DateTimeInterface) {
                    return Carbon::instance($when)->format('d.m.Y');
                }

                return Carbon::parse((string) $when)->format('d.m.Y');
            } catch (Throwable) {
                continue;
            }
        }

        return '';
    }

    /**
     * Аватар для публичного сайта с учётом delivery=local (переписывание CDN → /media/…).
     */
    public function publicAvatarUrl(): ?string
    {
        $raw = $this->avatar_url;
        if ($raw === null || $raw === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $raw) === 1) {
            return $raw;
        }

        return TenantPublicAssetResolver::resolve($raw, (int) $this->tenant_id) ?? $raw;
    }

    public static function statuses(): array
    {
        return [
            'draft' => 'Черновик',
            'pending' => 'На модерации',
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
