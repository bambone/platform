<?php

namespace App\Support\PageBuilder;

use App\PageBuilder\Expert\EditorialGalleryExternalArticlePreviewApplier;
use App\Services\LinkPreview\LinkPreviewHttpUrlValidator;

/**
 * Обнаружение типичных ошибок заполнения Expert: Галерея в data_json (аудит БД / сидеров).
 */
final class EditorialGalleryJsonAuditor
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<array{path: string, message: string}>
     */
    public static function collectIssues(array $data): array
    {
        $issues = [];
        $items = $data['items'] ?? null;
        if (! is_array($items)) {
            return $issues;
        }
        foreach ($items as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $prefix = 'items.'.$i;
            foreach (self::rowIssues($row) as $msg) {
                $issues[] = ['path' => $prefix, 'message' => $msg];
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function rowIssues(array $row): array
    {
        $out = [];
        $kind = trim((string) ($row['media_kind'] ?? ''));
        if ($kind === 'external_article') {
            return self::externalArticleRowIssues($row);
        }

        $imageUrl = trim((string) ($row['image_url'] ?? ''));
        $videoUrl = trim((string) ($row['video_url'] ?? ''));
        $posterUrl = trim((string) ($row['poster_url'] ?? ''));
        $caption = (string) ($row['caption'] ?? '');
        $embedUrl = trim((string) ($row['embed_share_url'] ?? ''));

        if ($imageUrl !== '' && self::suspiciousImageUrl($imageUrl)) {
            $out[] = 'image_url похож на страницу HTML, а не файл изображения.';
        }
        if ($videoUrl !== '' && self::suspiciousVideoFileUrl($videoUrl)) {
            $out[] = 'video_url похож на страницу VK/YouTube, а не файл видео — используйте тип «Видео (встраивание)».';
        }
        if ($posterUrl !== '' && (str_contains($posterUrl, '<') || str_contains(strtolower($posterUrl), 'iframe'))) {
            $out[] = 'poster_url содержит HTML/iframe вместо URL изображения.';
        }
        if ($caption !== '' && (str_contains($caption, '&quot;') || str_contains($caption, '&#') || preg_match('/[<>]/', $caption) === 1)) {
            $out[] = 'caption содержит HTML или сущности — замените на обычный текст.';
        }
        if ($embedUrl !== '' && str_contains($embedUrl, '<')) {
            $out[] = 'embed_share_url содержит HTML — укажите только URL страницы ролика.';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private static function externalArticleRowIssues(array $row): array
    {
        $out = [];
        $articleUrl = trim((string) ($row['article_url'] ?? ''));
        $articleOk = LinkPreviewHttpUrlValidator::validateForFetch($articleUrl);
        if ($articleUrl === '' || ! $articleOk['ok']) {
            $out[] = 'external_article: укажите полный article_url с http:// или https://.';
        }

        $titleManual = trim((string) ($row['article_title'] ?? ''));
        $titleFetched = trim((string) ($row['article_fetched_title'] ?? ''));
        if ($titleManual === '' && $titleFetched === '') {
            $out[] = 'external_article: нет заголовка (ни article_title, ни article_fetched_title).';
        }

        $mode = (string) ($row['article_image_mode'] ?? EditorialGalleryExternalArticlePreviewApplier::IMAGE_SUGGESTED);
        $override = trim((string) ($row['article_image_override_url'] ?? ''));

        if ($mode === EditorialGalleryExternalArticlePreviewApplier::IMAGE_TENANT_FILE && $override === '') {
            $out[] = 'external_article: режим tenant_file без article_image_override_url.';
        }

        if ($mode === EditorialGalleryExternalArticlePreviewApplier::IMAGE_EXTERNAL_URL) {
            $overrideOk = LinkPreviewHttpUrlValidator::validateForFetch($override);
            if ($override === '' || ! $overrideOk['ok']) {
                $out[] = 'external_article: для external_url нужен article_image_override_url с http(s).';
            }
        }

        return $out;
    }

    private static function suspiciousImageUrl(string $v): bool
    {
        $lower = strtolower($v);
        if (preg_match('#^site/#', $v) === 1) {
            return false;
        }
        if (str_starts_with($v, '/') && ! str_starts_with($v, '//')) {
            return ! self::pathHasImageExt($v);
        }
        if (! str_starts_with($v, 'http://') && ! str_starts_with($v, 'https://')) {
            return false;
        }
        $path = parse_url($v, PHP_URL_PATH);

        return is_string($path) && $path !== '' && ! self::pathHasImageExt($path);
    }

    private static function pathHasImageExt(string $path): bool
    {
        return preg_match('/\.(jpe?g|png|gif|webp|avif|svg)(?:\?|$)/i', $path) === 1;
    }

    private static function suspiciousVideoFileUrl(string $v): bool
    {
        $lower = strtolower($v);
        if (preg_match('/\.(mp4|webm|ogv)(\?|$)/i', $v) === 1) {
            return false;
        }
        if (preg_match('#^site/.+\.(mp4|webm|ogv)#i', $v) === 1) {
            return false;
        }

        return str_contains($lower, 'youtube.com/watch')
            || str_contains($lower, 'youtu.be/')
            || preg_match('~vk\.com/video~', $lower) === 1;
    }
}
