<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Tenant\Expert\ServiceProgramType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantServiceProgram extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_service_programs';

    protected $fillable = [
        'tenant_id',
        'slug',
        'title',
        'teaser',
        'description',
        'audience_json',
        'outcomes_json',
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
     * Human price from minor units (e.g. kopecks for RUB).
     */
    public function formattedPriceLabel(?string $currencyCode = 'RUB'): ?string
    {
        if ($this->price_amount === null) {
            return null;
        }
        if ($currencyCode === 'RUB') {
            $rub = $this->price_amount / 100;

            return number_format($rub, 0, ',', ' ').' ₽';
        }

        return (string) $this->price_amount;
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
}
