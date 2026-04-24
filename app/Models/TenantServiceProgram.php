<?php

namespace App\Models;

use App\MediaPresentation\Casts\PresentationDataCast;
use App\Models\Concerns\BelongsToTenant;
use App\Money\MoneyBindingRegistry;
use App\Support\Storage\TenantPublicAssetResolver;
use App\Tenant\Expert\ServiceProgramType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantServiceProgram extends Model
{
    use BelongsToTenant;

    /**
     * Единый лимит для slug в админке, публичных ссылках (/contacts?service=…), prefill и {@see \App\Http\Requests\StoreContactInquiryRequest}.
     * Колонка в БД может быть шире; публичный контракт — не длиннее этого значения.
     */
    public const SLUG_MAX_LENGTH = 64;

    /**
     * Публичный контракт: service=…, inquiry_service_slug, пути в URL и предсказуемые сегменты в storage.
     * Latin lowercase, цифры, дефис; без пробелов, слэшей, кириллицы. Нормализация: {@see self::normalizePublicSlugForStorage()}.
     */
    public const string PUBLIC_INQUIRY_SLUG_PCRE = '/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/';

    protected $table = 'tenant_service_programs';

    protected static function booted(): void
    {
        static::saving(function (self $m): void {
            $slug = self::normalizePublicSlugForStorage((string) ($m->slug ?? ''));
            if ($slug === '') {
                throw new \InvalidArgumentException('Укажите URL-идентификатор (slug).');
            }
            if (mb_strlen($slug, 'UTF-8') > self::SLUG_MAX_LENGTH) {
                throw new \InvalidArgumentException(
                    'URL-идентификатор (slug) не длиннее '.self::SLUG_MAX_LENGTH.' символов (публичные ссылки и поле заявки inquiry_service_slug).',
                );
            }
            if (! self::isPublicInquirySlugFormat($slug)) {
                throw new \InvalidArgumentException(
                    'URL-идентификатор: только латиница в стиле kebab-case, цифры и дефис (например: ppf, ppf-ceramic).',
                );
            }
            if (self::query()
                ->where('tenant_id', (int) $m->tenant_id)
                ->where('slug', $slug)
                ->when(
                    $m->exists,
                    fn (Builder $q) => $q->whereKeyNot((int) $m->id),
                )
                ->exists()
            ) {
                throw new \InvalidArgumentException('URL-идентификатор уже используется для другой программы/услуги в этом клиенте.');
            }
            $m->slug = $slug;
            $meta = is_array($m->catalog_meta_json) ? $m->catalog_meta_json : [];
            $mode = trim((string) ($meta['booking_mode'] ?? ''));
            if ($mode === '' || ! in_array($mode, ['instant', 'confirm', 'quote'], true)) {
                $meta['booking_mode'] = 'confirm';
            } else {
                $meta['booking_mode'] = $mode;
            }
            if (! array_key_exists('has_landing', $meta)) {
                $meta['has_landing'] = true;
            }
            if (! array_key_exists('show_in_catalog', $meta)) {
                $meta['show_in_catalog'] = true;
            }
            if (! array_key_exists('show_on_home', $meta)) {
                $meta['show_on_home'] = true;
            }
            $m->catalog_meta_json = $meta;
        });
    }

    protected $fillable = [
        'tenant_id',
        'slug',
        'title',
        'teaser',
        'description',
        'audience_json',
        'outcomes_json',
        'cover_image_ref',
        'cover_mobile_ref',
        'cover_image_alt',
        'cover_object_position',
        'cover_presentation_json',
        'catalog_meta_json',
        'duration_label',
        'price_amount',
        'price_prefix',
        'format_label',
        'program_type',
        'is_featured',
        'is_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'audience_json' => 'array',
            'outcomes_json' => 'array',
            'cover_presentation_json' => PresentationDataCast::class,
            'catalog_meta_json' => 'array',
            'is_featured' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Featured first, then sort_order, id (table-backed service_program_cards).
     *
     * @return Collection<int, self>
     */
    public static function forServiceProgramCards(int $tenantId, array $data): Collection
    {
        $limit = max(1, min(48, (int) ($data['limit'] ?? 12)));
        $include = self::normalizeSlugList($data['include_slugs'] ?? null);
        $exclude = self::normalizeSlugList($data['exclude_slugs'] ?? null);

        $base = static::query()
            ->where('tenant_id', $tenantId)
            ->where('is_visible', true)
            ->when($include !== [], fn (Builder $q) => $q->whereIn('slug', $include))
            ->when($exclude !== [], fn (Builder $q) => $q->whereNotIn('slug', $exclude));

        $featured = (clone $base)
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $rest = (clone $base)
            ->where('is_featured', false)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $merged = $featured->concat($rest)->unique('id')->take($limit);

        return new Collection($merged->all());
    }

    /**
     * Visible programs by sort_order (pricing_cards); optional slug filters.
     *
     * @return Collection<int, self>
     */
    public static function forPricingCards(int $tenantId, array $data): Collection
    {
        $include = self::normalizeSlugList($data['include_slugs'] ?? null);
        $exclude = self::normalizeSlugList($data['exclude_slugs'] ?? null);

        return static::query()
            ->where('tenant_id', $tenantId)
            ->where('is_visible', true)
            ->when($include !== [], fn (Builder $q) => $q->whereIn('slug', $include))
            ->when($exclude !== [], fn (Builder $q) => $q->whereNotIn('slug', $exclude))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Single runtime entry for the {@code pricing_cards} section: DB rows + normalized manual fallback.
     * Slug filters stay in {@see self::forPricingCards()} (include/exclude from section {@code data_json}).
     *
     * @return array{programs: Collection<int, self>, manual_cards: list<array{title: string, body: string}>}
     */
    public static function resolvePricingCardsSection(int $tenantId, array $data): array
    {
        $manual = [];
        $rawManual = $data['manual_cards'] ?? null;
        if (is_array($rawManual)) {
            foreach ($rawManual as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $manual[] = [
                    'title' => $title,
                    'body' => trim((string) ($row['body'] ?? '')),
                ];
            }
        }

        return [
            'programs' => self::forPricingCards($tenantId, $data),
            'manual_cards' => $manual,
        ];
    }

    public static function isAllowedProgramType(string $value): bool
    {
        return ServiceProgramType::tryFrom($value) !== null;
    }

    /**
     * Human price from storage minor units via tenant money layer.
     */
    public function formattedPriceLabel(?Tenant $tenant = null): ?string
    {
        if ($this->price_amount === null) {
            return null;
        }
        $t = $tenant ?? currentTenant();
        if ($t === null) {
            return (string) $this->price_amount;
        }

        return tenant_money_format((int) $this->price_amount, MoneyBindingRegistry::TENANT_SERVICE_PROGRAM_PRICE_AMOUNT, $t);
    }

    /**
     * Desktop / tall crop URL for program card media pane (R2 public key or https).
     */
    public function coverDesktopPublicUrl(?Tenant $tenant): ?string
    {
        return TenantPublicAssetResolver::resolveForTenantModel(
            trim((string) $this->cover_image_ref) !== '' ? trim((string) $this->cover_image_ref) : null,
            $tenant
        );
    }

    /**
     * Mobile / wide crop URL; falls back to desktop when unset.
     */
    public function coverMobilePublicUrl(?Tenant $tenant): ?string
    {
        $mobile = TenantPublicAssetResolver::resolveForTenantModel(
            trim((string) $this->cover_mobile_ref) !== '' ? trim((string) $this->cover_mobile_ref) : null,
            $tenant
        );
        if ($mobile !== null) {
            return $mobile;
        }

        return $this->coverDesktopPublicUrl($tenant);
    }

    public function coverImageAlt(): string
    {
        $a = trim((string) $this->cover_image_alt);

        return $a !== '' ? $a : (string) $this->title;
    }

    /**
     * @return list<string>
     */
    private static function normalizeSlugList(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_string($raw)) {
            $parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);

            return $parts !== false ? array_values(array_unique($parts)) : [];
        }
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Сегменты пути в public storage, query-параметры; должен совпадать с {@see self::assertPublicInquirySlugForFormSave()}.
     */
    public static function normalizePublicSlugForStorage(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }

        return Str::slug($trimmed, '-', 'en');
    }

    public static function isPublicInquirySlugFormat(string $slug): bool
    {
        if ($slug === '' || mb_strlen($slug, 'UTF-8') > self::SLUG_MAX_LENGTH) {
            return false;
        }

        return (bool) preg_match(self::PUBLIC_INQUIRY_SLUG_PCRE, $slug);
    }

    /**
     * @return non-empty-string
     *
     * @throws ValidationException
     */
    public static function normalizedPublicInquirySlugOrFailForTenant(string $raw, int $tenantId, ?int $ignoreServiceProgramId): string
    {
        $slug = self::normalizePublicSlugForStorage($raw);
        if ($slug === '') {
            throw ValidationException::withMessages(['slug' => 'Укажите URL-идентификатор.']);
        }
        if (mb_strlen($slug, 'UTF-8') > self::SLUG_MAX_LENGTH) {
            throw ValidationException::withMessages(['slug' => 'URL-идентификатор: не длиннее '.self::SLUG_MAX_LENGTH.' символов.']);
        }
        if (! self::isPublicInquirySlugFormat($slug)) {
            throw ValidationException::withMessages(['slug' => 'Только латиница, цифры и дефис (kebab-case), например ppf, ppf-ceramic, без слэшей и пробелов.']);
        }
        if (self::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when(
                $ignoreServiceProgramId !== null,
                fn (Builder $q) => $q->whereKeyNot($ignoreServiceProgramId),
            )
            ->exists()
        ) {
            throw ValidationException::withMessages(['slug' => 'Такой URL-идентификатор уже используется в этом клиенте.']);
        }

        return $slug;
    }
}
