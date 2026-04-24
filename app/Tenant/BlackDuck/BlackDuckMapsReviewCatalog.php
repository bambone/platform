<?php

declare(strict_types=1);

namespace App\Tenant\BlackDuck;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Курируемые отзывы с публичных карт (2ГИС, Яндекс) для посадочных услуг Black Duck.
 * Уникальные авторы в пуле; на каждую посадочную — {@see REVIEWS_PER_LANDING} карточек + CTA на карты в шаблоне.
 *
 * @phpstan-type PoolRow array{
 *   name: string,
 *   city: string,
 *   platform: '2gis'|'yandex',
 *   text: string,
 *   avatar: ?string,
 *   headline?: string
 * }
 */
final class BlackDuckMapsReviewCatalog
{
    public const SOURCE = 'maps_curated';

    /** Максимум карточек отзыва на посадочной; фактическое число может быть 3–4, если пул короче. */
    public const REVIEWS_PER_LANDING = 4;

    /**
     * @return list<string>
     */
    public static function landingSlugOrder(?int $tenantId = null): array
    {
        if ($tenantId !== null && BlackDuckServiceProgramCatalog::databaseHasCatalog($tenantId)) {
            $out = [];
            foreach (BlackDuckServiceProgramCatalog::visibleProgramsOrdered($tenantId) as $p) {
                $meta = is_array($p->catalog_meta_json) ? $p->catalog_meta_json : [];
                if (! (bool) ($meta['has_landing'] ?? true)) {
                    continue;
                }
                $slug = (string) $p->slug;
                if (str_starts_with($slug, '#')) {
                    continue;
                }
                $out[] = $slug;
            }

            return $out;
        }
        $out = [];
        foreach (BlackDuckServiceRegistry::all() as $r) {
            if (! $r['has_landing'] || str_starts_with((string) $r['slug'], '#')) {
                continue;
            }
            $out[] = (string) $r['slug'];
        }

        return $out;
    }

    /**
     * @return list<PoolRow>
     */
    public static function pool(): array
    {
        $phpPath = database_path('data/black_duck_maps_reviews_pool.php');
        if (is_readable($phpPath)) {
            $loaded = require $phpPath;
            $normalized = self::normalizePoolRows(is_array($loaded) ? $loaded : []);
            if ($normalized !== []) {
                return $normalized;
            }
        }

        $path = database_path('data/black_duck_maps_reviews_pool.json');
        if (! is_readable($path)) {
            return self::fallbackPool();
        }
        $raw = json_decode((string) file_get_contents($path), true);
        if (! is_array($raw)) {
            return self::fallbackPool();
        }
        $out = self::normalizePoolRows($raw);

        return $out !== [] ? $out : self::fallbackPool();
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<PoolRow>
     */
    private static function normalizePoolRows(array $raw): array
    {
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $text = trim((string) ($row['text'] ?? ''));
            if ($name === '' || $text === '') {
                continue;
            }
            $platform = (string) ($row['platform'] ?? '2gis');
            if ($platform !== 'yandex' && $platform !== '2gis') {
                $platform = '2gis';
            }
            /** @var '2gis'|'yandex' $p */
            $p = $platform === 'yandex' ? 'yandex' : '2gis';
            $avatar = isset($row['avatar']) && is_string($row['avatar']) && $row['avatar'] !== '' ? $row['avatar'] : null;
            $headline = isset($row['headline']) && is_string($row['headline']) ? trim($row['headline']) : '';
            $item = [
                'name' => $name,
                'city' => trim((string) ($row['city'] ?? 'Челябинск')) ?: 'Челябинск',
                'platform' => $p,
                'text' => $text,
                'avatar' => $avatar,
            ];
            if ($headline !== '') {
                $item['headline'] = $headline;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * Резерв, если JSON ещё не развёрнут на окружении.
     *
     * @return list<PoolRow>
     */
    private static function fallbackPool(): array
    {
        /**
         * Резерв, если data/black_duck_maps_reviews_pool.{php,json} нет. Нужен разумный размер,
         * т.к. сид/refresh раздают по {@see REVIEWS_PER_LANDING} на посадочную с циклическим выбором.
         */
        return [
            [
                'name' => 'Тимофей',
                'city' => 'Челябинск',
                'platform' => '2gis',
                'text' => 'Отличный сервис! Мастер Игорь устранил и остановил трещину на лобовом стекле. Работа выполнена аккуратно, профессионально и быстро.',
                'avatar' => null,
            ],
            [
                'name' => 'Ростислав Сухоруков',
                'city' => 'Челябинск',
                'platform' => 'yandex',
                'text' => 'Остался очень доволен: доброжелательные сотрудники, всегда на связи, проконсультировали по ценам и срокам. Всё сделали вовремя, машина стала как новая, без следов и запахов.',
                'avatar' => null,
            ],
            [
                'name' => 'Анна',
                'city' => 'Челябинск',
                'platform' => '2gis',
                'text' => 'Сделали полировку фар: разница как день и ночь, рекомендую — аккуратно, без пыли в салоне.',
                'avatar' => null,
            ],
            [
                'name' => 'Дмитрий',
                'city' => 'Миасс',
                'platform' => 'yandex',
                'text' => 'Записывался заранее, приняли вовремя, сроки сказали честно и выдержали. За кузовом ухаживаю регулярно, теперь только сюда.',
                'avatar' => null,
            ],
            [
                'name' => 'Екатерина',
                'city' => 'Челябинск',
                'platform' => '2gis',
                'text' => 'Пленку на капоте заменили без разводов, показали под лампой результат — внимательно к деталям.',
                'avatar' => null,
            ],
            [
                'name' => 'Сергей',
                'city' => 'Копейск',
                'platform' => 'yandex',
                'text' => 'Съездил с царапиной на двери — согласовали картину по телефону, вечером забрал в срок.',
                'avatar' => null,
            ],
            [
                'name' => 'Олег',
                'city' => 'Челябинск',
                'platform' => '2gis',
                'text' => 'Химчистка сидений: запаха химии не осталось, влажность выгнали, салон в норме.',
                'avatar' => null,
            ],
            [
                'name' => 'Марина',
                'city' => 'Челябинск',
                'platform' => 'yandex',
                'text' => 'Консультация по PPF: объяснили пакеты, показали образцы, ничего лишнего не навязывали.',
                'avatar' => null,
            ],
        ];
    }

    /**
     * Распределяет {@see pool()} по {@see landingSlugOrder()}; при «нулевой» доле посадочной
     * даёт минимум одну карточку с циклическим переиспользованием пула (см. warning в логе).
     *
     * @return list<array<string, mixed>> rows for reviews table insert
     */
    public static function rowsForDatabaseSeed(?int $tenantId = null): array
    {
        $slugs = self::landingSlugOrder($tenantId);
        $pool = self::pool();
        if ($pool === [] || $slugs === []) {
            return [];
        }

        $slugN = count($slugs);
        $poolN = count($pool);
        /** @var list<int> $counts */
        $counts = array_fill(0, $slugN, intdiv($poolN, $slugN));
        $rem = $poolN % $slugN;
        for ($i = 0; $i < $rem; $i++) {
            $counts[$i]++;
        }
        if (in_array(0, $counts, true) && $poolN > 0) {
            Log::warning('BlackDuckMapsReviewCatalog: review pool is smaller than landing count; at least one review per landing via cyclic pool reuse (extra rows beyond raw pool size).', [
                'tenant_id' => $tenantId,
                'pool_count' => $poolN,
                'landing_count' => $slugN,
            ]);
            foreach ($counts as $i => $c) {
                if ($c === 0) {
                    $counts[$i] = 1;
                }
            }
        }

        $now = now()->toDateString();
        $out = [];
        $idx = 0;
        foreach ($slugs as $si => $slug) {
            $take = $counts[$si];
            for ($k = 0; $k < $take; $k++) {
                $row = $pool[$idx % $poolN];
                $idx++;
                $meta = ['maps_platform' => $row['platform']];
                if (! empty($row['avatar']) && is_string($row['avatar'])) {
                    $meta['avatar_external_url'] = $row['avatar'];
                }
                $text = (string) $row['text'];
                $headline = trim((string) ($row['headline'] ?? ''));
                if ($headline === '') {
                    $headline = match ($row['platform']) {
                        'yandex' => 'Яндекс Карты',
                        default => '2ГИС',
                    };
                }
                $out[] = [
                    'name' => $row['name'],
                    'city' => $row['city'],
                    'headline' => $headline,
                    'text_short' => Str::limit($text, 220, '…'),
                    'text_long' => $text,
                    'text' => $text,
                    'rating' => 5,
                    'category_key' => $slug,
                    'source' => self::SOURCE,
                    'status' => 'published',
                    'is_featured' => false,
                    'sort_order' => ($k + 1) * 10,
                    'date' => $now,
                    'media_type' => 'text',
                    'meta_json' => $meta,
                ];
            }
        }

        return $out;
    }
}
