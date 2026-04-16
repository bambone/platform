<?php

declare(strict_types=1);

namespace App\PageBuilder\Expert;

use App\Services\LinkPreview\ExternalArticlePreviewData;

/**
 * Слияние результата link-preview в состояние элемента repeater (MVP-политики: refresh vs смена URL).
 *
 * @phpstan-type ItemState array<string, mixed>
 */
final class EditorialGalleryExternalArticlePreviewApplier
{
    public const FETCH_IDLE = 'idle';

    public const FETCH_LOADING = 'loading';

    public const FETCH_OK = 'ok';

    public const FETCH_FAILED = 'failed';

    public const IMAGE_SUGGESTED = 'suggested';

    public const IMAGE_TENANT_FILE = 'tenant_file';

    public const IMAGE_EXTERNAL_URL = 'external_url';

    public const IMAGE_NONE = 'none';

    /**
     * Нормализация ввода URL для сравнения (trim).
     */
    public static function normalizeArticleUrl(string $url): string
    {
        return trim($url);
    }

    /**
     * Нормализованная «идентичность документа» для дедупа авто-fetch при blur:
     * схема/хост/путь в нижнем регистре; путь без хвостового "/" (кроме корня);
     * из query убраны типичные маркетинговые параметры (utm_*, gclid, …).
     * Сравнивать с тем же ключом для {@see $item['article_last_fetch_canonical_url']} после успешного fetch.
     */
    public static function urlMaterialIdentityForAutoFetchDedupe(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return strtolower($url);
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '');
        if ($path === '') {
            $path = '/';
        } elseif ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/') ?: '/';
        }
        $query = (string) ($parts['query'] ?? '');
        $qs = [];
        if ($query !== '') {
            parse_str($query, $qs);
            foreach (array_keys($qs) as $key) {
                $lk = strtolower((string) $key);
                if (str_starts_with($lk, 'utm_')) {
                    unset($qs[$key]);

                    continue;
                }
                if (in_array($lk, ['gclid', 'fbclid', 'msclkid', 'mc_eid', 'igshid'], true)) {
                    unset($qs[$key]);
                }
            }
            ksort($qs, SORT_STRING);
        }
        $built = http_build_query($qs);

        return $scheme.'://'.$host.$path.($built !== '' ? '?'.$built : '');
    }

    /**
     * Пропустить авто-fetch при blur: тот же нормализованный ввод и уже успешный снимок;
     * либо тот же документ по каноническому URL (после strip UTM), см. {@see urlMaterialIdentityForAutoFetchDedupe()}.
     */
    public static function shouldSkipAutoFetch(string $normalizedInputUrl, array $item): bool
    {
        if ($normalizedInputUrl === '') {
            return true;
        }
        if (($item['article_fetch_status'] ?? '') === self::FETCH_LOADING) {
            return true;
        }
        $last = self::normalizeArticleUrl((string) ($item['article_last_fetched_input_url'] ?? ''));

        if ($normalizedInputUrl === $last && ($item['article_fetch_status'] ?? '') === self::FETCH_OK) {
            return true;
        }

        if (($item['article_fetch_status'] ?? '') === self::FETCH_OK) {
            $canonical = trim((string) ($item['article_last_fetch_canonical_url'] ?? ''));
            if ($canonical !== '') {
                $idInput = self::urlMaterialIdentityForAutoFetchDedupe($normalizedInputUrl);
                $idCanon = self::urlMaterialIdentityForAutoFetchDedupe($canonical);

                return $idInput !== '' && $idInput === $idCanon;
            }
        }

        return false;
    }

    /**
     * После успешного или неуспешного fetch при авто-триггере (blur / первая вставка).
     * Если URL изменился относительно {@see $previousNormalizedInputUrl} — MVP: сбросить article_* и картинку к suggested, затем заполнить article_* из fetched при success.
     *
     * @param  ItemState  $item  текущее состояние строки до применения патча (article_last_fetched_input_url — значение до fetch)
     * @return ItemState патч (все ключи, которые нужно записать)
     */
    public static function applyAutoFetchResult(
        array $item,
        ExternalArticlePreviewData $data,
        string $normalizedInputUrl,
    ): array {
        $lastFetched = self::normalizeArticleUrl((string) ($item['article_last_fetched_input_url'] ?? ''));
        $urlChanged = $lastFetched !== '' && $lastFetched !== $normalizedInputUrl;

        $patch = self::baseFetchPatch($data, $normalizedInputUrl);

        if ($urlChanged && $data->ok) {
            $patch['article_title'] = $data->title;
            $patch['article_description'] = $data->description;
            $patch['article_site_name'] = $data->siteName !== '' ? $data->siteName : $data->domain;
            $patch['article_image_mode'] = self::IMAGE_SUGGESTED;
            $patch['article_image_override_url'] = '';
        } elseif ($urlChanged && ! $data->ok) {
            $patch['article_title'] = '';
            $patch['article_description'] = '';
            $patch['article_site_name'] = '';
            $patch['article_image_mode'] = self::IMAGE_SUGGESTED;
            $patch['article_image_override_url'] = '';
        } elseif ($data->ok && ($lastFetched === '' || ! self::hasAnyArticleText($item))) {
            $patch['article_title'] = $data->title;
            $patch['article_description'] = $data->description;
            $patch['article_site_name'] = $data->siteName !== '' ? $data->siteName : $data->domain;
        }

        return $patch;
    }

    /**
     * Кнопка «Обновить превью»: только fetched_* и suggested (в {@see baseFetchPatch}); article_* и override-картинка не в патче.
     *
     * @param  ItemState  $item
     * @return ItemState
     */
    public static function applyRefreshResult(array $item, ExternalArticlePreviewData $data, string $normalizedInputUrl): array
    {
        return self::baseFetchPatch($data, $normalizedInputUrl);
    }

    /**
     * @param  ItemState  $item
     */
    public static function applyLoadingState(string $normalizedInputUrl, array $item): array
    {
        return [
            'article_fetch_status' => self::FETCH_LOADING,
            'article_fetch_error' => '',
            'article_last_fetched_input_url' => $item['article_last_fetched_input_url'] ?? '',
            'article_last_fetch_canonical_url' => $item['article_last_fetch_canonical_url'] ?? '',
        ];
    }

    /**
     * @param  ItemState  $item
     */
    private static function hasAnyArticleText(array $item): bool
    {
        return trim((string) ($item['article_title'] ?? '')) !== ''
            || trim((string) ($item['article_description'] ?? '')) !== ''
            || trim((string) ($item['article_site_name'] ?? '')) !== '';
    }

    /**
     * @return ItemState
     */
    private static function baseFetchPatch(ExternalArticlePreviewData $data, string $normalizedInputUrl): array
    {
        if (! $data->ok) {
            return [
                'article_fetched_title' => '',
                'article_fetched_description' => '',
                'article_fetched_site_name' => '',
                'article_domain' => '',
                'article_canonical_url' => '',
                'article_suggested_image_url' => '',
                'article_suggested_image_width' => null,
                'article_suggested_image_height' => null,
                'article_fetched_at' => $data->fetchedAt->format(\DateTimeInterface::ATOM),
                'article_fetch_status' => self::FETCH_FAILED,
                'article_fetch_error' => $data->errorCode !== '' ? $data->errorCode : 'fetch_failed',
                'article_last_fetched_input_url' => $normalizedInputUrl,
                'article_last_fetch_canonical_url' => '',
            ];
        }

        return [
            'article_fetched_title' => $data->title,
            'article_fetched_description' => $data->description,
            'article_fetched_site_name' => $data->siteName !== '' ? $data->siteName : $data->domain,
            'article_domain' => $data->domain,
            'article_canonical_url' => $data->canonicalUrl,
            'article_suggested_image_url' => $data->imageUrl,
            'article_suggested_image_width' => $data->imageWidth,
            'article_suggested_image_height' => $data->imageHeight,
            'article_fetched_at' => $data->fetchedAt->format(\DateTimeInterface::ATOM),
            'article_fetch_status' => self::FETCH_OK,
            'article_fetch_error' => '',
            'article_last_fetched_input_url' => $normalizedInputUrl,
            'article_last_fetch_canonical_url' => $data->canonicalUrl,
        ];
    }
}
