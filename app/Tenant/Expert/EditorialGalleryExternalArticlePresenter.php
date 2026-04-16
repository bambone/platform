<?php

declare(strict_types=1);

namespace App\Tenant\Expert;

use App\PageBuilder\Expert\EditorialGalleryExternalArticlePreviewApplier;

/**
 * Публичная карточка внешнего материала: effective поля из снимка data_json (без runtime fetch HTML).
 *
 * Режим {@see EditorialGalleryExternalArticlePreviewApplier::IMAGE_SUGGESTED}: обложка — hotlink на внешний URL
 * из превью (ограничение MVP: доступность картинки зависит от третьей стороны).
 */
final class EditorialGalleryExternalArticlePresenter
{
    /**
     * @param  array<string, mixed>  $row  элемент items
     * @return array{
     *     href: string,
     *     target: ?string,
     *     rel: string,
     *     title: string,
     *     description: string,
     *     siteLabel: string,
     *     domain: string,
     *     imageUrl: string,
     *     imageIsExternalHotlink: bool,
     * }|null null если нет валидной ссылки
     */
    public static function fromRow(array $row): ?array
    {
        $href = trim((string) ($row['article_url'] ?? ''));
        if ($href === '' || ! str_starts_with(strtolower($href), 'http')) {
            return null;
        }

        $title = trim((string) ($row['article_title'] ?? ''));
        if ($title === '') {
            $title = trim((string) ($row['article_fetched_title'] ?? ''));
        }
        $description = trim((string) ($row['article_description'] ?? ''));
        if ($description === '') {
            $description = trim((string) ($row['article_fetched_description'] ?? ''));
        }
        $site = trim((string) ($row['article_site_name'] ?? ''));
        if ($site === '') {
            $site = trim((string) ($row['article_fetched_site_name'] ?? ''));
        }
        $domain = trim((string) ($row['article_domain'] ?? ''));

        $openNew = array_key_exists('open_in_new_tab', $row)
            ? (bool) $row['open_in_new_tab']
            : true;

        $mode = (string) ($row['article_image_mode'] ?? EditorialGalleryExternalArticlePreviewApplier::IMAGE_SUGGESTED);
        $override = trim((string) ($row['article_image_override_url'] ?? ''));
        $suggested = trim((string) ($row['article_suggested_image_url'] ?? ''));

        $imageUrl = '';
        $hotlink = false;

        if ($mode === EditorialGalleryExternalArticlePreviewApplier::IMAGE_TENANT_FILE && $override !== '') {
            $imageUrl = ExpertBrandMediaUrl::resolve($override);
        } elseif ($mode === EditorialGalleryExternalArticlePreviewApplier::IMAGE_EXTERNAL_URL && $override !== '') {
            if (str_starts_with(strtolower($override), 'http://') || str_starts_with(strtolower($override), 'https://')) {
                $imageUrl = $override;
                $hotlink = true;
            }
        } elseif ($mode === EditorialGalleryExternalArticlePreviewApplier::IMAGE_SUGGESTED && $suggested !== '') {
            if (str_starts_with(strtolower($suggested), 'http://') || str_starts_with(strtolower($suggested), 'https://')) {
                $imageUrl = $suggested;
                $hotlink = true;
            }
        }

        return [
            'href' => $href,
            'target' => $openNew ? '_blank' : null,
            'rel' => $openNew ? 'noopener noreferrer' : '',
            'title' => $title,
            'description' => $description,
            'siteLabel' => $site !== '' ? $site : $domain,
            'domain' => $domain,
            'imageUrl' => $imageUrl,
            'imageIsExternalHotlink' => $hotlink,
        ];
    }
}
