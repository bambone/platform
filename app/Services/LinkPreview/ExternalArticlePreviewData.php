<?php

declare(strict_types=1);

namespace App\Services\LinkPreview;

use DateTimeImmutable;

/**
 * Результат парсинга страницы для полей {@code article_fetched_*} (не для прямой записи в {@code article_*} при refresh).
 */
final readonly class ExternalArticlePreviewData
{
    public function __construct(
        public string $title,
        public string $description,
        public string $siteName,
        public string $domain,
        public string $canonicalUrl,
        public string $imageUrl,
        public ?int $imageWidth,
        public ?int $imageHeight,
        public DateTimeImmutable $fetchedAt,
        public bool $ok,
        public string $errorCode,
        public string $errorMessage,
        public string $finalUrl,
    ) {}

    public static function failed(string $finalUrl, string $errorCode, string $errorMessage): self
    {
        return new self(
            title: '',
            description: '',
            siteName: '',
            domain: '',
            canonicalUrl: '',
            imageUrl: '',
            imageWidth: null,
            imageHeight: null,
            fetchedAt: new DateTimeImmutable,
            ok: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            finalUrl: $finalUrl,
        );
    }
}
